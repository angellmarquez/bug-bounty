<?php

namespace App\Services\Adjuntos;

use App\Models\Adjunto;
use App\Models\Apelacion;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Pgp\PgpService;
use GdImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Fotos de evidencia de informes y apelaciones.
 *
 * Al subir: se comprueba que el contenido sea de verdad PNG/JPEG/WebP, se
 * re-codifica la imagen (desaparecen EXIF, GPS y cualquier dato escondido tras
 * los píxeles), se calcula su SHA-256 y se cifra con PGP a las claves del
 * informe antes de escribirla en el disco. Al servirla: se descifra al vuelo,
 * se verifica la huella y se entrega con cabeceras que impiden ejecutarla.
 */
class AdjuntoService
{
    public function __construct(private readonly PgpService $pgp) {}

    /**
     * Reglas de validación para el campo `fotos[]` de un formulario.
     *
     * @return array<string, array<int, string>>
     */
    public static function reglas(): array
    {
        return [
            'fotos' => ['sometimes', 'array', 'max:'.config('adjuntos.max_por_entidad')],
            'fotos.*' => ['file', 'mimetypes:'.implode(',', config('adjuntos.mimes')), 'max:'.config('adjuntos.max_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        $maxMb = round(self::maxKbEfectivo() / 1024, 1);

        return [
            'fotos.array' => 'Las fotos no se enviaron correctamente.',
            'fotos.max' => 'Puedes adjuntar como máximo '.config('adjuntos.max_por_entidad').' fotos.',
            'fotos.*.file' => 'Una de las fotos no se pudo subir.',
            'fotos.*.uploaded' => "Una de las fotos no se pudo subir: pesa más de lo que admite el servidor (máximo {$maxMb} MB).",
            'fotos.*.mimetypes' => 'Solo se admiten fotos PNG, JPG o WebP.',
            'fotos.*.max' => "Cada foto puede pesar como máximo {$maxMb} MB.",
        ];
    }

    /**
     * Peso máximo por foto en KB que de verdad se puede subir: el de la configuración,
     * salvo que `upload_max_filesize` de PHP sea menor (entonces PHP corta antes).
     */
    public static function maxKbEfectivo(): int
    {
        $php = UploadedFile::getMaxFilesize();
        $configurado = (int) config('adjuntos.max_kb');

        return $php > 0 ? min($configurado, (int) floor($php / 1024)) : $configurado;
    }

    /**
     * Peso máximo en KB de todas las fotos de un envío: `post_max_size` de PHP, con un
     * margen para el resto del formulario. Si se supera, PHP descarta la petición entera.
     */
    public static function maxTotalKb(): int
    {
        $valor = trim((string) ini_get('post_max_size'));
        $bytes = (int) $valor;

        $bytes *= match (strtolower(substr($valor, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return $bytes > 0 ? (int) floor($bytes / 1024) - 256 : PHP_INT_MAX;
    }

    /**
     * Cuántas fotos más admite la entidad.
     */
    public function cupoRestante(Reporte|Apelacion $entidad): int
    {
        return max(0, (int) config('adjuntos.max_por_entidad') - $entidad->adjuntos()->count());
    }

    /**
     * Procesa, cifra y guarda las fotos, y las asocia a la entidad.
     *
     * @param  array<int, UploadedFile>  $fotos
     * @return Collection<int, Adjunto>
     *
     * @throws ValidationException si alguna foto no es una imagen válida o se supera el cupo
     */
    public function guardar(Reporte|Apelacion $entidad, array $fotos, User $autor, ?Empresa $empresa = null): Collection
    {
        if ($fotos === []) {
            return collect();
        }

        if (count($fotos) > $this->cupoRestante($entidad)) {
            throw ValidationException::withMessages([
                'fotos' => 'Se supera el máximo de '.config('adjuntos.max_por_entidad').' fotos.',
            ]);
        }

        $preparadas = $this->preparar($fotos, $empresa);

        try {
            return DB::transaction(fn (): Collection => $this->asociar($entidad, $preparadas, $autor));
        } catch (Throwable $e) {
            $this->descartar($preparadas);

            throw $e;
        }
    }

    /**
     * Procesa y cifra las fotos y las escribe en el disco, sin asociarlas a nada.
     * Si una falla, se borran las que ya se habían escrito.
     *
     * @param  array<int, UploadedFile>  $fotos
     * @return array<int, array{nombre_original: string, mime: string, tamano: int, ancho: int, alto: int, sha256: string, disco: string, ruta: string, clave_huella: string}>
     */
    public function preparar(array $fotos, ?Empresa $empresa = null): array
    {
        $disco = (string) config('adjuntos.disco');
        $preparadas = [];

        try {
            foreach ($fotos as $foto) {
                $imagen = $this->procesar($foto);
                $cifrado = $this->pgp->cifrarArchivo($imagen['binario'], $empresa);
                $ruta = config('adjuntos.directorio').'/'.now()->format('Y/m').'/'.Str::uuid().'.pgp';

                if (! Storage::disk($disco)->put($ruta, $cifrado['contenido'])) {
                    throw new \RuntimeException('No se pudo guardar la foto.');
                }

                $preparadas[] = [
                    'nombre_original' => $this->nombreSeguro($foto, $imagen['mime']),
                    'mime' => $imagen['mime'],
                    'tamano' => strlen($imagen['binario']),
                    'ancho' => $imagen['ancho'],
                    'alto' => $imagen['alto'],
                    'sha256' => hash('sha256', $imagen['binario']),
                    'disco' => $disco,
                    'ruta' => $ruta,
                    'clave_huella' => $cifrado['clave_huella'],
                ];
            }
        } catch (Throwable $e) {
            $this->descartar($preparadas);

            throw $e;
        }

        return $preparadas;
    }

    /**
     * Borra del disco fotos preparadas que no llegaron a guardarse.
     *
     * @param  array<int, array{disco: string, ruta: string}>  $preparadas
     */
    public function descartar(array $preparadas): void
    {
        foreach ($preparadas as $preparada) {
            Storage::disk($preparada['disco'])->delete($preparada['ruta']);
        }
    }

    /**
     * Asocia a una entidad fotos ya preparadas con {@see preparar()}.
     *
     * @param  array<int, array<string, mixed>>  $preparadas
     * @return Collection<int, Adjunto>
     */
    public function asociar(Model $entidad, array $preparadas, User $autor): Collection
    {
        $adjuntos = collect($preparadas)->map(fn (array $datos): Adjunto => Adjunto::query()->create([
            ...$datos,
            'adjuntable_type' => $entidad->getMorphClass(),
            'adjuntable_id' => $entidad->getKey(),
            'subido_por' => $autor->id,
        ]));

        Auditoria::registrar('adjuntos.subidos', $entidad, [
            'adjuntos' => $adjuntos->map(fn (Adjunto $a): array => ['id' => $a->id, 'sha256' => $a->sha256])->all(),
        ], $autor->id);

        return $adjuntos;
    }

    public function eliminar(Adjunto $adjunto): void
    {
        Auditoria::registrar('adjuntos.eliminado', $adjunto->adjuntable, ['adjunto_id' => $adjunto->id, 'sha256' => $adjunto->sha256]);

        Storage::disk($adjunto->disco)->delete($adjunto->ruta);
        $adjunto->delete();
    }

    /**
     * Descifra la foto y la entrega con cabeceras que impiden ejecutarla,
     * cachearla fuera del navegador o filtrar la URL.
     */
    public function responder(Adjunto $adjunto): Response
    {
        $cifrado = Storage::disk($adjunto->disco)->get($adjunto->ruta);
        abort_if($cifrado === null, 404, 'La foto ya no está disponible.');

        $binario = $this->pgp->descifrarArchivo($cifrado, $adjunto);

        if (! hash_equals($adjunto->sha256, hash('sha256', $binario))) {
            Auditoria::registrar('adjuntos.integridad_fallida', $adjunto);
            abort(409, 'La foto no supera la verificación de integridad.');
        }

        return response($binario, 200, [
            'Content-Type' => $adjunto->mime,
            'Content-Length' => (string) strlen($binario),
            'Content-Disposition' => 'inline; filename="evidencia-'.$adjunto->id.'.'.$adjunto->extension().'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    /**
     * Datos de una foto para el navegador (sin ruta, disco ni clave).
     *
     * @return array{id: int, nombre: string, mime: string, tamano: int, ancho: int, alto: int, sha256: string, url: string, created_at: string|null}
     */
    public function resumen(Adjunto $adjunto, string $url): array
    {
        return [
            'id' => $adjunto->id,
            'nombre' => $adjunto->nombre_original,
            'mime' => $adjunto->mime,
            'tamano' => $adjunto->tamano,
            'ancho' => $adjunto->ancho,
            'alto' => $adjunto->alto,
            'sha256' => $adjunto->sha256,
            'url' => $url,
            'created_at' => $adjunto->created_at?->toISOString(),
        ];
    }

    /**
     * Comprueba que el archivo sea una imagen real y la vuelve a codificar desde
     * sus píxeles: así se descartan metadatos (EXIF/GPS) y cualquier contenido
     * adicional (scripts, archivos pegados al final, políglotas).
     *
     * @return array{binario: string, mime: string, ancho: int, alto: int}
     */
    private function procesar(UploadedFile $foto): array
    {
        $ruta = (string) $foto->getRealPath();
        $info = @getimagesize($ruta);

        if ($info === false || ! in_array($info['mime'], config('adjuntos.mimes'), true)) {
            $this->rechazar('no es una imagen PNG, JPG o WebP válida', $foto);
        }

        [$ancho, $alto] = $info;

        if ($ancho < 1 || $alto < 1 || $ancho * $alto > (int) config('adjuntos.max_pixeles')) {
            $this->rechazar('tiene una resolución demasiado grande', $foto);
        }

        $mime = $info['mime'];
        $imagen = match ($mime) {
            'image/png' => @imagecreatefrompng($ruta),
            'image/webp' => @imagecreatefromwebp($ruta),
            default => @imagecreatefromjpeg($ruta),
        };

        if (! $imagen instanceof GdImage) {
            $this->rechazar('está dañada o no se puede leer', $foto);
        }

        if (! imageistruecolor($imagen)) {
            imagepalettetotruecolor($imagen);
        }

        if ($mime === 'image/jpeg') {
            $imagen = $this->orientar($imagen, $ruta);
        }

        $imagen = $this->reducir($imagen);

        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);

        ob_start();
        $ok = match ($mime) {
            'image/png' => imagepng($imagen, null, 6),
            'image/webp' => imagewebp($imagen, null, 85),
            default => imagejpeg($imagen, null, 88),
        };
        $binario = (string) ob_get_clean();

        if (! $ok || $binario === '') {
            $this->rechazar('no se pudo procesar', $foto);
        }

        return ['binario' => $binario, 'mime' => $mime, 'ancho' => imagesx($imagen), 'alto' => imagesy($imagen)];
    }

    /**
     * Las fotos de móvil guardan el giro en EXIF; al descartar EXIF hay que
     * aplicarlo a los píxeles o la foto se vería de lado.
     */
    private function orientar(GdImage $imagen, string $ruta): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($ruta);
        $orientacion = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $girada = match ($orientacion) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, -90, 0),
            8 => imagerotate($imagen, 90, 0),
            default => $imagen,
        };

        return $girada instanceof GdImage ? $girada : $imagen;
    }

    private function reducir(GdImage $imagen): GdImage
    {
        $maximo = (int) config('adjuntos.max_lado');
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        if (max($ancho, $alto) <= $maximo) {
            return $imagen;
        }

        $escala = $maximo / max($ancho, $alto);
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $reducida = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($reducida, false);
        imagesavealpha($reducida, true);
        imagecopyresampled($reducida, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return $reducida;
    }

    private function nombreSeguro(UploadedFile $foto, string $mime): string
    {
        $base = pathinfo($foto->getClientOriginalName(), PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[^\pL\pN _.-]+/u', '_', $base), ' ._');
        $base = Str::limit($base === '' ? 'foto' : $base, 100, '');

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return "{$base}.{$extension}";
    }

    private function rechazar(string $motivo, UploadedFile $foto): never
    {
        throw ValidationException::withMessages([
            'fotos' => 'La foto «'.Str::limit($foto->getClientOriginalName(), 60).'» '.$motivo.'.',
        ]);
    }
}

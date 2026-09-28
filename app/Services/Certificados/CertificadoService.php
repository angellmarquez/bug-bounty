<?php

namespace App\Services\Certificados;

use App\Models\CertificadoDivulgacion;
use App\Models\ClavePgpPlataforma;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Notificaciones\Notificador;
use App\Services\Pgp\PgpService;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Servicio encargado de emitir y verificar criptográficamente los
 * Certificados de Divulgación Responsable para informes cerrados como resueltos.
 */
class CertificadoService
{
    /**
     * Versión del payload canónico que se sella. La v1 (certificados anteriores) no incluía
     * título, categoría, autor ni fecha del informe; se sigue verificando con su propio formato.
     */
    public const VERSION_ACTUAL = 2;

    public function __construct(
        protected PgpService $pgp,
        protected Notificador $notificador,
    ) {}

    /**
     * Obtiene el certificado existente o emite uno nuevo sellado con SHA-256 y firma PGP.
     * Al emitirlo por primera vez avisa al investigador.
     *
     * @throws InvalidArgumentException si el informe no está cerrado como resuelto.
     */
    public function obtenerOCrear(Reporte $reporte, ?User $actor = null): CertificadoDivulgacion
    {
        if (! $reporte->admiteCertificado()) {
            throw new InvalidArgumentException('Solo se pueden emitir certificados para informes cerrados como resueltos.');
        }

        $existente = $reporte->certificado;
        if ($existente !== null) {
            return $existente;
        }

        $reporte->loadMissing(['programa.empresa', 'investigador']);

        $empresa = $reporte->programa->empresa;
        $empresaNombre = $empresa !== null
            ? ($empresa->nombre_comercial ?? $empresa->razon_social)
            : 'Huella';

        $datos = [
            'version' => self::VERSION_ACTUAL,
            'numero_reporte' => $reporte->numero_reporte,
            'titulo' => $reporte->titulo,
            'categoria' => $reporte->categoria ?? 'Vulnerabilidad de Seguridad',
            // Un informe puede cerrarse sin CVSS calculado: queda sin clasificar (null).
            'severidad' => $reporte->severidad?->value,
            'cvss_score' => (float) ($reporte->puntuacion_cvss ?? 0.0),
            'cvss_vector' => $reporte->vector_cvss,
            'programa_nombre' => $reporte->programa->nombre,
            'empresa_nombre' => $empresaNombre,
            'investigador_alias' => $reporte->investigador->name,
            'investigador_id' => $reporte->investigador_id,
            'fecha_reporte' => ($reporte->enviado_en ?? $reporte->created_at ?? now())->toIso8601String(),
            'fecha_resolucion' => ($reporte->cerrado_en ?? now())->toIso8601String(),
        ];

        $codigo = 'BB-CERT-'.strtoupper(Str::random(10));
        $huella = $this->calcularHuellaCanonica($codigo, $reporte->id, $datos);

        $clavePlataforma = $this->pgp->asegurarClave();
        $firmaPgp = $this->pgp->sign($huella);

        $certificado = CertificadoDivulgacion::query()->create([
            'reporte_id' => $reporte->id,
            'codigo' => $codigo,
            'huella' => $huella,
            'firma_pgp' => $firmaPgp,
            'clave_huella' => $clavePlataforma->huella,
            'datos' => $datos,
            'emitido_por_id' => $actor?->id,
        ]);

        $reporte->setRelation('certificado', $certificado);
        $this->notificador->certificadoEmitido($reporte, $actor);

        return $certificado;
    }

    /**
     * Verifica la autenticidad, integridad y vigencia del certificado:
     * 1. Recalcula el SHA-256 de los datos canónicos.
     * 2. Verifica la firma digital PGP contra la clave de la plataforma.
     * 3. Comprueba que el informe siga existiendo y cerrado como resuelto.
     *
     * @return array{
     *     valido: bool,
     *     hash_valido: bool,
     *     firma_valida: bool,
     *     vigente: bool,
     *     huella: string,
     *     huella_calculada: string,
     *     clave_huella: string|null,
     * }
     */
    public function verificar(CertificadoDivulgacion $certificado): array
    {
        $huellaEsperada = $this->calcularHuellaCanonica(
            $certificado->codigo,
            $certificado->reporte_id,
            $certificado->datos,
        );

        $hashValido = hash_equals($huellaEsperada, $certificado->huella);

        // Primero con la clave pública que figura en el certificado (guardada en la base): así uno
        // firmado con una clave ya reemplazada sigue verificándose. Los emitidos antes de fijar la
        // clave firmante pudieron firmarse con otra clave del llavero de la plataforma: se aceptan
        // si esa firma la reconoce el propio llavero (solo contiene claves de la plataforma).
        try {
            $clavePublica = $this->clavePublicaDe($certificado)?->clave_publica;
            $firmaValida = ($clavePublica !== null && $this->pgp->verify($certificado->huella, $certificado->firma_pgp, $clavePublica))
                || $this->pgp->verify($certificado->huella, $certificado->firma_pgp);
        } catch (Throwable $e) {
            report($e);
            $firmaValida = false;
        }
        $vigente = $certificado->reporte?->admiteCertificado() ?? false;

        return [
            'valido' => $hashValido && $firmaValida,
            'hash_valido' => $hashValido,
            'firma_valida' => $firmaValida,
            'vigente' => $vigente,
            'huella' => $certificado->huella,
            'huella_calculada' => $huellaEsperada,
            'clave_huella' => $certificado->clave_huella,
        ];
    }

    /**
     * Código QR (SVG) que lleva a la página pública de verificación del certificado: quien lo
     * tenga impreso lo comprueba con la cámara del móvil. Negro sobre blanco para que se lea
     * también en papel.
     */
    public function qrVerificacion(CertificadoDivulgacion $certificado): string
    {
        $url = route('certificados.verificar', ['codigo' => $certificado->codigo]);

        $svg = (new Writer(new ImageRenderer(
            new RendererStyle(160, 1, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0, 0, 0))),
            new SvgImageBackEnd,
        )))->writeString($url);

        // Sin la declaración XML: el SVG se incrusta tal cual en la página.
        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    /**
     * Clave pública con la que se firmó el certificado, para verificarlo fuera de la plataforma.
     */
    public function clavePublicaDe(CertificadoDivulgacion $certificado): ?ClavePgpPlataforma
    {
        if ($certificado->clave_huella === null) {
            return null;
        }

        return ClavePgpPlataforma::query()->where('huella', $certificado->clave_huella)->first();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function calcularHuellaCanonica(string $codigo, int $reporteId, array $datos): string
    {
        $version = (int) ($datos['version'] ?? 1);

        $payload = [
            'codigo' => $codigo,
            'reporte_id' => $reporteId,
            'numero_reporte' => (string) ($datos['numero_reporte'] ?? ''),
            'severidad' => (string) ($datos['severidad'] ?? ''),
            'cvss_score' => (float) ($datos['cvss_score'] ?? 0.0),
            'cvss_vector' => $datos['cvss_vector'] ?? null,
            'programa' => (string) ($datos['programa_nombre'] ?? ''),
            'empresa' => (string) ($datos['empresa_nombre'] ?? ''),
            'investigador_id' => (int) ($datos['investigador_id'] ?? 0),
            'fecha_resolucion' => (string) ($datos['fecha_resolucion'] ?? ''),
        ];

        // v2: todo lo que muestra el certificado queda sellado (antes se podía cambiar el título
        // o el nombre del investigador sin que la verificación lo notara).
        if ($version >= 2) {
            $payload = [
                'version' => $version,
                ...$payload,
                'titulo' => (string) ($datos['titulo'] ?? ''),
                'categoria' => (string) ($datos['categoria'] ?? ''),
                'investigador' => (string) ($datos['investigador_alias'] ?? ''),
                'fecha_reporte' => (string) ($datos['fecha_reporte'] ?? ''),
            ];
        }

        // Las opciones de codificación forman parte del formato: la v1 se selló sin JSON_UNESCAPED_UNICODE.
        $opciones = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | ($version >= 2 ? JSON_UNESCAPED_UNICODE : 0);

        return hash('sha256', json_encode($payload, $opciones));
    }
}

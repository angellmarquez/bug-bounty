<?php

namespace App\Services\Pgp;

use App\Models\Auditoria;
use App\Models\ClavePgpEmpresa;
use App\Models\ClavePgpPlataforma;
use App\Models\Empresa;
use App\Services\Notificaciones\Notificador;
use App\Services\Pgp\Contracts\PgpDriver;
use App\Services\Pgp\DataObjects\PgpKeyInfo;
use App\Services\Pgp\Exceptions\PgpDriverUnavailableException;
use App\Services\Pgp\Exceptions\PgpException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

/**
 * Fachada del motor PGP interno de la plataforma.
 *
 * Resuelve el driver configurado (see config/pgp.php) y expone las operaciones
 * de cifrado/firma de alto nivel, además de gestionar el par de claves de la
 * plataforma persistido en {@see ClavePgpPlataforma}.
 */
class PgpService
{
    public function __construct(private readonly PgpDriver $driver) {}

    public function driver(): PgpDriver
    {
        return $this->driver;
    }

    /**
     * Indica si un valor persistido parece un bloque PGP cifrado.
     */
    private function esMensajeCifrado(string $valor): bool
    {
        return str_contains($valor, 'BEGIN') && str_contains($valor, 'PGP');
    }

    public function driverName(): string
    {
        return $this->driver->name();
    }

    public function usesFallback(): bool
    {
        return $this->driver->name() === 'fallback';
    }

    /**
     * Indica si el driver configurado está disponible en el entorno actual.
     */
    public function available(): bool
    {
        return $this->driver->available();
    }

    /**
     * Genera un nuevo par de claves para la plataforma y lo persiste activo.
     *
     * @param  array<string, mixed>  $options  identidad, algoritmo, expiración, ...
     *
     * @throws PgpDriverUnavailableException si el driver no está disponible
     */
    public function generatePlatformKeyPair(array $options = []): ClavePgpPlataforma
    {
        // En producción una clave sin contraseña queda expuesta si se roba el servidor: no se crea.
        if (app()->isProduction() && ! $this->usesFallback() && (string) config('pgp.gpg.passphrase') === '') {
            throw new PgpException('En producción la clave PGP debe protegerse con una contraseña: define PGP_KEY_PASSWORD en el .env antes de crearla.');
        }

        // Con GnuPG real el llavero rechaza dos claves con la misma identidad: sin la marca
        // única, regenerar la clave (pgp:setup --force) fallaría con "Ya existe una clave".
        $identity = (string) ($options['identity'] ?? $this->identidadUnica());

        $info = $this->driver->generateKeyPair($options + compact('identity'));

        $publicKey = $this->driver->exportPublicKey($info->fingerprint);
        $privateKey = $this->driver->exportPrivateKey($info->fingerprint);

        return app('db')->transaction(function () use ($info, $identity, $publicKey, $privateKey): ClavePgpPlataforma {
            ClavePgpPlataforma::query()->where('activa', true)->update(['activa' => false]);

            return ClavePgpPlataforma::query()->create([
                'id_clave' => $info->idClave,
                'huella' => $info->fingerprint,
                'clave_publica' => $publicKey,
                'clave_privada' => $privateKey,
                'identidad' => $identity,
                'algoritmo' => $info->algoritmo,
                'bits' => $info->bits,
                'creada_en' => $info->creadaEn?->toDateString(),
                'expira_en' => $info->expiraEn,
                'activa' => true,
            ]);
        });
    }

    /**
     * Devuelve la clave activa y, si todavía no hay ninguna, la crea (una sola vez, aunque lleguen
     * varias peticiones a la vez). Así el cifrado se configura solo y un informe nunca se rechaza
     * ni se guarda en claro por «falta de clave».
     *
     * @throws PgpException si no se puede crear (GnuPG no disponible, sin contraseña en producción…)
     */
    public function asegurarClave(string $origen = 'automatico'): ClavePgpPlataforma
    {
        $existente = $this->platformKey();

        if ($existente !== null) {
            return $existente;
        }

        $candado = Cache::lock('pgp:crear-clave', (int) config('pgp.creacion.candado_segundos', 180));

        try {
            return $candado->block((int) config('pgp.creacion.espera_segundos', 60), function () use ($origen): ClavePgpPlataforma {
                // Otra petición pudo crearla mientras esperábamos el candado.
                return $this->platformKey() ?? $this->crearClave($origen);
            });
        } catch (LockTimeoutException) {
            $clave = $this->platformKey();

            if ($clave !== null) {
                return $clave;
            }

            throw new PgpException('Otra petición está creando la clave de cifrado de la plataforma; inténtalo de nuevo en unos segundos.');
        }
    }

    /**
     * Crea una clave nueva, con identidad única, y deja constancia: Auditoría (como acción del
     * sistema) y aviso a los administradores. Nunca registra la clave privada.
     */
    public function crearClave(string $origen, ?string $identidad = null): ClavePgpPlataforma
    {
        $clave = $this->generatePlatformKeyPair([
            'identity' => $identidad ?? $this->identidadUnica(),
            'algorithm' => (string) config('pgp.gpg.algorithm'),
            'expires_in' => '1y',
        ]);

        try {
            // Se genera sola (al instalar o al recibir el primer informe): nunca es
            // "de" quien la disparó, por eso usuario_id va explícito en null.
            Auditoria::registrar('pgp.clave_generada', $clave, [
                'huella' => $clave->huella,
                'identidad' => $clave->identidad,
                'origen' => $origen,
            ], usuarioId: null);
        } catch (Throwable $e) {
            report($e);
        }

        app(Notificador::class)->claveGenerada($clave, $origen);

        return $clave;
    }

    /**
     * La identidad configurada más una marca única (fecha y código corto), para que una clave nueva
     * nunca choque con otra del llavero del servidor (GnuPG rechaza dos con la misma identidad).
     */
    public function identidadUnica(?string $base = null): string
    {
        $base = trim($base ?? (string) config('pgp.identity'));
        $marca = 'auto '.now()->format('Ymd').'-'.Str::lower(Str::random(4));

        if (preg_match('/^(.*?)\s*<([^>]+)>$/', $base, $partes) === 1) {
            return trim($partes[1])." ({$marca}) <{$partes[2]}>";
        }

        return "{$base} ({$marca})";
    }

    /**
     * Devuelve el par de claves de la plataforma actualmente activo.
     */
    public function platformKey(): ?ClavePgpPlataforma
    {
        return ClavePgpPlataforma::query()->where('activa', true)->first();
    }

    /**
     * Devuelve la clave PGP de una empresa y, si todavía no tiene, la crea sola
     * (una sola vez, aunque lleguen varias peticiones a la vez) -- mismo
     * patrón que {@see asegurarClave()} para la de custodia. La empresa no
     * sube ni gestiona nada: es PGP interno.
     *
     * @throws PgpException si no se puede crear (GnuPG no disponible, ...)
     */
    public function claveDeEmpresa(Empresa $empresa): ClavePgpEmpresa
    {
        $existente = $empresa->clavePgp()->where('activa', true)->first();

        if ($existente !== null) {
            return $existente;
        }

        $candado = Cache::lock("pgp:crear-clave-empresa:{$empresa->id}", (int) config('pgp.creacion.candado_segundos', 180));

        try {
            return $candado->block((int) config('pgp.creacion.espera_segundos', 60), function () use ($empresa): ClavePgpEmpresa {
                return $empresa->clavePgp()->where('activa', true)->first() ?? $this->crearClaveDeEmpresa($empresa);
            });
        } catch (LockTimeoutException) {
            $clave = $empresa->clavePgp()->where('activa', true)->first();

            if ($clave !== null) {
                return $clave;
            }

            throw new PgpException("Otra petición está creando la clave de cifrado de la empresa #{$empresa->id}; inténtalo de nuevo en unos segundos.");
        }
    }

    /**
     * Genera la clave PGP de una empresa. Igual que {@see crearClave()}, nunca
     * registra la clave privada en la auditoría.
     */
    private function crearClaveDeEmpresa(Empresa $empresa): ClavePgpEmpresa
    {
        if (app()->isProduction() && ! $this->usesFallback() && (string) config('pgp.gpg.passphrase') === '') {
            throw new PgpException('En producción la clave PGP debe protegerse con una contraseña: define PGP_KEY_PASSWORD en el .env antes de crearla.');
        }

        $identity = $this->identidadUnica("{$empresa->razon_social} <seguridad@localhost>");
        $info = $this->driver->generateKeyPair([
            'identity' => $identity,
            'algorithm' => (string) config('pgp.gpg.algorithm'),
            'expires_in' => '1y',
        ]);

        $publicKey = $this->driver->exportPublicKey($info->fingerprint);
        $privateKey = $this->driver->exportPrivateKey($info->fingerprint);

        $clave = app('db')->transaction(function () use ($empresa, $info, $identity, $publicKey, $privateKey): ClavePgpEmpresa {
            ClavePgpEmpresa::query()->where('empresa_id', $empresa->id)->update(['activa' => false]);

            return ClavePgpEmpresa::query()->create([
                'empresa_id' => $empresa->id,
                'id_clave' => $info->idClave,
                'huella' => $info->fingerprint,
                'clave_publica' => $publicKey,
                'clave_privada' => $privateKey,
                'identidad' => $identity,
                'algoritmo' => $info->algoritmo,
                'bits' => $info->bits,
                'creada_en' => $info->creadaEn?->toDateString(),
                'expira_en' => $info->expiraEn,
                'activa' => true,
            ]);
        });

        try {
            Auditoria::registrar('pgp.clave_empresa_generada', $clave, [
                'huella' => $clave->huella,
                'empresa_id' => $empresa->id,
            ], usuarioId: null);
        } catch (Throwable $e) {
            report($e);
        }

        return $clave;
    }

    /**
     * Las huellas a las que se cifra el contenido de una empresa: su propia
     * clave (se crea sola si hace falta) + la de custodia, para que
     * moderación/admin puedan auditar sin depender de la clave de la empresa.
     * Sin empresa (programa sin dueño), solo la de custodia.
     *
     * @return array<int, string>
     */
    private function destinatariosPara(?Empresa $empresa): array
    {
        $custodia = $this->platformKey() ?? $this->asegurarClave();
        $destinatarios = [$custodia->huella];

        if ($empresa !== null) {
            $destinatarios[] = $this->claveDeEmpresa($empresa)->huella;
        }

        return $destinatarios;
    }

    /**
     * Reimporta la clave activa de la plataforma en el keyring local del
     * driver a partir de lo persistido en la base de datos.
     *
     * Necesario cuando el proceso arranca con el keyring vacío (disco
     * efímero: un contenedor recién creado sin disco persistente) aunque la
     * clave siga activa en {@see ClavePgpPlataforma}: sin este paso,
     * cifrar/descifrar fallaría con "no se encontró la clave" pese a que la
     * fila en base de datos sigue ahí.
     *
     * @return bool si había una clave activa que reimportar
     */
    public function restaurarEnKeyring(): bool
    {
        $clave = $this->platformKey();

        if ($clave === null) {
            return false;
        }

        $this->driver->importPrivateKey($clave->clave_privada);

        return true;
    }

    /**
     * Reimporta la clave PGP de cada empresa (guardada en la base) en el
     * keyring local. Igual necesidad que {@see restaurarEnKeyring()} y por el
     * mismo motivo: disco efímero en cada reinicio del contenedor.
     *
     * @return int cuántas claves de empresa se reimportaron
     */
    public function restaurarClavesDeEmpresaEnKeyring(): int
    {
        $restauradas = 0;

        ClavePgpEmpresa::query()->where('activa', true)->each(function (ClavePgpEmpresa $clave) use (&$restauradas): void {
            $this->driver->importPrivateKey($clave->clave_privada);
            $restauradas++;
        });

        return $restauradas;
    }

    /**
     * @param  string|array<int, string>  $recipient
     */
    public function encrypt(string $message, string|array $recipient): string
    {
        return $this->driver->encrypt($message, $recipient);
    }

    /**
     * Descifra un mensaje con la clave privada de la plataforma.
     */
    public function decrypt(string $armoredMessage): string
    {
        return $this->driver->decrypt($armoredMessage);
    }

    /**
     * Importa una clave pública y devuelve sus metadatos.
     */
    public function importPublicKey(string $armoredPublicKey): PgpKeyInfo
    {
        return $this->driver->importKey($armoredPublicKey);
    }

    /**
     * Calcula la huella de una clave pública armored.
     */
    public function fingerprint(string $armoredPublicKey): string
    {
        return $this->driver->fingerprint($armoredPublicKey);
    }

    /**
     * Exporta la clave pública armored del par activo de la plataforma.
     */
    public function platformPublicKey(): string
    {
        return ($this->platformKey() ?? $this->asegurarClave())->clave_publica;
    }

    /**
     * Cifra el contenido de un reporte (descripcion + PoC) a la clave de la
     * empresa dueña del programa + la de custodia (moderación/admin), para
     * que ambas puedan descifrarlo de forma independiente.
     *
     * @param  array<int|string, mixed>  $poc
     * @return array{descripcion: string, poc: string|null, clave_huella: string}
     */
    public function cifrarReporte(string $descripcion, array $poc = [], ?Empresa $empresa = null): array
    {
        $destinatarios = $this->destinatariosPara($empresa);

        $pocCifrado = $poc === []
            ? null
            : $this->encrypt(json_encode($poc, JSON_THROW_ON_ERROR), $destinatarios);

        return [
            'descripcion' => $this->encrypt($descripcion, $destinatarios),
            'poc' => $pocCifrado,
            'clave_huella' => $destinatarios[0],
        ];
    }

    /**
     * Descifra el contenido de un reporte custodiado en la base de datos.
     *
     * Los valores que no sean bloques PGP (datos legacy en claro) se devuelven
     * tal cual. El PoC se devuelve decodificado. Cada descifrado exitoso queda
     * en Auditoría (quién, cuándo, qué entidad) -- ver AGENTS.md, "logs de
     * seguridad".
     *
     * @return array{descripcion: string, poc: array<int|string, mixed>|null, clave_huella: string|null}
     */
    public function descifrarReporte(string $descripcion, ?string $poc = null, ?Model $entidad = null): array
    {
        $huboCifrado = $this->esMensajeCifrado($descripcion) || ($poc !== null && $this->esMensajeCifrado($poc));

        $descripcionLegible = $this->esMensajeCifrado($descripcion)
            ? $this->decrypt($descripcion)
            : $descripcion;

        $pocLegible = null;

        if ($poc !== null) {
            $contenido = $this->esMensajeCifrado($poc) ? $this->decrypt($poc) : $poc;

            try {
                $pocLegible = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $pocLegible = null;
            }

            if (! is_array($pocLegible)) {
                $pocLegible = null;
            }
        }

        if ($huboCifrado && $entidad !== null) {
            $this->auditarDescifrado($entidad);
        }

        return [
            'descripcion' => $descripcionLegible,
            'poc' => $pocLegible,
            'clave_huella' => $this->platformKey()?->huella,
        ];
    }

    /**
     * Deja constancia de cada vez que se descifra contenido confidencial:
     * quién lo pidió, cuándo y sobre qué entidad. No guarda el contenido.
     */
    private function auditarDescifrado(Model $entidad): void
    {
        try {
            Auditoria::registrar('pgp.contenido_descifrado', $entidad);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Cifra el contenido sensible de un programa (descripcion y bugs_buscados)
     * con la clave pública de la plataforma para su custodia en la base de datos.
     * El alcance (objetivos) se cifra aparte con {@see cifrarObjetivo()}, porque
     * vive en su propia tabla, una fila por objetivo.
     *
     * @return array{descripcion: string, bugs_buscados: string|null, clave_huella: string}
     */
    public function cifrarPrograma(string $descripcion, ?string $bugsBuscados = null, ?Empresa $empresa = null): array
    {
        $destinatarios = $this->destinatariosPara($empresa);

        return [
            'descripcion' => $this->encrypt($descripcion, $destinatarios),
            'bugs_buscados' => ($bugsBuscados === null || $bugsBuscados === '')
                ? $bugsBuscados
                : $this->encrypt($bugsBuscados, $destinatarios),
            'clave_huella' => $destinatarios[0],
        ];
    }

    /**
     * Descifra el contenido de un programa custodiado en la base de datos.
     * Los valores que no sean bloques PGP (datos legacy en claro, como los de
     * los seeders de demo) se devuelven tal cual.
     *
     * @return array{descripcion: string, bugs_buscados: string|null}
     */
    public function descifrarPrograma(string $descripcion, ?string $bugsBuscados = null, ?Model $entidad = null): array
    {
        $huboCifrado = $this->esMensajeCifrado($descripcion) || ($bugsBuscados !== null && $this->esMensajeCifrado($bugsBuscados));

        $legible = [
            'descripcion' => $this->esMensajeCifrado($descripcion) ? $this->decrypt($descripcion) : $descripcion,
            'bugs_buscados' => ($bugsBuscados !== null && $this->esMensajeCifrado($bugsBuscados))
                ? $this->decrypt($bugsBuscados)
                : $bugsBuscados,
        ];

        if ($huboCifrado && $entidad !== null) {
            $this->auditarDescifrado($entidad);
        }

        return $legible;
    }

    /**
     * Cifra el valor (dominio/IP/API) y la descripción de un objetivo de programa.
     *
     * @return array{valor: string, descripcion: string|null}
     */
    public function cifrarObjetivo(string $valor, ?string $descripcion = null, ?Empresa $empresa = null): array
    {
        $destinatarios = $this->destinatariosPara($empresa);

        return [
            'valor' => $this->encrypt($valor, $destinatarios),
            'descripcion' => ($descripcion === null || $descripcion === '')
                ? $descripcion
                : $this->encrypt($descripcion, $destinatarios),
        ];
    }

    /**
     * Descifra el valor y la descripción de un objetivo de programa.
     *
     * @return array{valor: string, descripcion: string|null}
     */
    public function descifrarObjetivo(string $valor, ?string $descripcion = null): array
    {
        return [
            'valor' => $this->esMensajeCifrado($valor) ? $this->decrypt($valor) : $valor,
            'descripcion' => ($descripcion !== null && $this->esMensajeCifrado($descripcion))
                ? $this->decrypt($descripcion)
                : $descripcion,
        ];
    }

    /**
     * Firma un mensaje con la clave de la plataforma.
     */
    public function sign(string $message): string
    {
        return $this->driver->sign($message);
    }

    /**
     * Verifica una firma contra un mensaje.
     */
    public function verify(string $message, string $armoredSignature, ?string $publicKey = null): bool
    {
        return $this->driver->verify($message, $armoredSignature, $publicKey);
    }
}

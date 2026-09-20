<?php

namespace App\Services\Pgp;

use App\Models\ClavePgpPlataforma;
use App\Services\Pgp\Contracts\PgpDriver;
use App\Services\Pgp\DataObjects\PgpKeyInfo;
use App\Services\Pgp\Exceptions\PgpDriverUnavailableException;
use App\Services\Pgp\Exceptions\PgpException;
use JsonException;

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
        $identity = (string) ($options['identity'] ?? config('pgp.identity'));

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
     * Devuelve el par de claves de la plataforma actualmente activo.
     */
    public function platformKey(): ?ClavePgpPlataforma
    {
        return ClavePgpPlataforma::query()->where('activa', true)->first();
    }

    /**
     * Cifra un mensaje para un destinatario (huella o clave pública armored).
     */
    public function encrypt(string $message, string $recipient): string
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
        $platform = $this->platformKey();

        if ($platform === null) {
            throw new PgpException(
                'La plataforma aún no tiene un par de claves PGP. Ejecuta php artisan pgp:setup.',
            );
        }

        return $platform->clave_publica;
    }

    /**
     * Cifra el contenido de un reporte (descripcion + PoC) con la clave
     * pública de la plataforma para su custodia en la base de datos.
     *
     * @param  array<int|string, mixed>  $poc
     * @return array{descripcion: string, poc: string|null, clave_huella: string}
     */
    public function cifrarReporte(string $descripcion, array $poc = []): array
    {
        $plataforma = $this->platformKey();

        if ($plataforma === null) {
            throw new PgpException(
                'La plataforma no tiene un par de claves PGP activo. Ejecuta php artisan pgp:setup.',
            );
        }

        $pocCifrado = $poc === []
            ? null
            : $this->encrypt(json_encode($poc, JSON_THROW_ON_ERROR), $plataforma->huella);

        return [
            'descripcion' => $this->encrypt($descripcion, $plataforma->huella),
            'poc' => $pocCifrado,
            'clave_huella' => $plataforma->huella,
        ];
    }

    /**
     * Descifra el contenido de un reporte custodiado en la base de datos.
     *
     * Los valores que no sean bloques PGP (datos legacy en claro) se devuelven
     * tal cual. El PoC se devuelve decodificado.
     *
     * @return array{descripcion: string, poc: array<int|string, mixed>|null, clave_huella: string|null}
     */
    public function descifrarReporte(string $descripcion, ?string $poc = null): array
    {
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

        return [
            'descripcion' => $descripcionLegible,
            'poc' => $pocLegible,
            'clave_huella' => $this->platformKey()?->huella,
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

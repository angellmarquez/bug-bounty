<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Cifra en reposo con un secreto propio (`PGP_STORAGE_KEY`), no con `APP_KEY`.
 *
 * `APP_KEY` protege sesiones, otras columnas `encrypted` y la firma de
 * cookies: si se filtra por cualquier motivo ajeno al PGP, no debería llevarse
 * puestas también las claves privadas PGP de la plataforma y de cada empresa.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class CifradoConClavePgp implements CastsAttributes
{
    /**
     * @param  Model  $model
     * @param  array<string, mixed>  $attributes
     */
    public function get($model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->encrypter()->decryptString($value);
    }

    /**
     * @param  Model  $model
     * @param  array<string, mixed>  $attributes
     */
    public function set($model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->encrypter()->encryptString((string) $value);
    }

    private function encrypter(): Encrypter
    {
        $key = (string) config('pgp.storage_key');

        if ($key === '') {
            throw new RuntimeException(
                'Falta PGP_STORAGE_KEY en el .env: es el secreto que protege las claves privadas PGP en la base de datos (separado de APP_KEY a propósito). '
                .'Generá uno con: php artisan tinker --execute="echo \'base64:\'.base64_encode(random_bytes(32));"',
            );
        }

        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }

        return new Encrypter($key, 'aes-256-cbc');
    }
}

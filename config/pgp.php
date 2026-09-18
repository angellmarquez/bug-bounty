<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver PGP
    |--------------------------------------------------------------------------
    |
    | 'auto'      : usa el binario de GnuPG si está disponible; si no, cae al
    |               driver de respaldo (solo local/testing).
    | 'gpg'       : fuerza el binario de GnuPG.
    | 'fallback'  : driver de respaldo (FAKE, sin confidencialidad real).
    |
    | El driver de respaldo está prohibido en producción.
    |
    */

    'driver' => env('PGP_DRIVER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Driver de binario GnuPG
    |--------------------------------------------------------------------------
    |
    */

    'gpg' => [
        'binary' => env('PGP_BINARY', 'gpg'),
        'home' => env('PGP_GNUPG_HOME', storage_path('app/pgp/gpg')),
        'algorithms' => ['ed25519', 'rsa4096'],
        'algorithm' => env('PGP_ALGORITHM', 'ed25519'),
        'passphrase' => env('PGP_KEY_PASSWORD', ''),
        'timeout' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver de respaldo (local/testing)
    |--------------------------------------------------------------------------
    |
    | Simula el contrato de GnuPG sin proporcionar cifrado real. El secreto
    | derivado de APP_KEY permite mantener los datos cifrados consistentes
    | entre peticiones del mismo entorno (no sirve entre entornos).
    |
    */

    'fallback' => [
        'store' => env('PGP_FALLBACK_STORE', storage_path('app/pgp/fallback')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Identidad de la plataforma
    |--------------------------------------------------------------------------
    |
    | Usada al generar el par de claves con `php artisan pgp:setup`.
    |
    */

    'identity' => env('PGP_IDENTITY', 'Plataforma BugBounty <seguridad@localhost>'),

];

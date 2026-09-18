<?php

namespace App\Services\Pgp\Contracts;

use App\Services\Pgp\DataObjects\PgpKeyInfo;
use App\Services\Pgp\Exceptions\PgpDriverUnavailableException;
use App\Services\Pgp\Exceptions\PgpException;

interface PgpDriver
{
    /**
     * Nombre identificador del driver (p.ej. "gpg" o "fallback").
     */
    public function name(): string;

    /**
     * Indica si el driver es usable en el entorno actual.
     */
    public function available(): bool;

    /**
     * Genera un par de claves y devuelve la información de la clave pública.
     *
     * @param  array<string, mixed>  $options  identidad, algoritmo, expiración, ...
     *
     * @throws PgpDriverUnavailableException si el driver no está disponible
     */
    public function generateKeyPair(array $options = []): PgpKeyInfo;

    /**
     * Importa una clave pública ASCII-armored y devuelve su información.
     *
     * @throws PgpException si la clave no es válida
     */
    public function importKey(string $armoredPublicKey): PgpKeyInfo;

    /**
     * Exporta la clave pública (ASCII-armored) correspondiente a una huella.
     *
     * @throws PgpException si la clave no es local
     */
    public function exportPublicKey(string $fingerprint): string;

    /**
     * Exporta la clave privada (ASCII-armored) correspondiente a una huella.
     *
     * @throws PgpException si la clave privada no es local
     */
    public function exportPrivateKey(string $fingerprint): string;

    /**
     * Calcula la huella de una clave pública ASCII-armored.
     *
     * @throws PgpException si la clave no es válida
     */
    public function fingerprint(string $armoredPublicKey): string;

    /**
     * Cifra un mensaje para un destinatario (huella o clave pública armored).
     *
     * @throws PgpException si no puede cifrarse
     */
    public function encrypt(string $message, string $recipient): string;

    /**
     * Descifra un mensaje ASCII-armored con la clave privada de la plataforma.
     *
     * @throws PgpException si no puede descifrarse
     */
    public function decrypt(string $armoredMessage): string;

    /**
     * Firma un mensaje y devuelve la firma ASCII-armored.
     *
     * @throws PgpException si no puede firmarse
     */
    public function sign(string $message, ?string $privateKey = null): string;

    /**
     * Verifica una firma ASCII-armored contra un mensaje.
     *
     * @param  string|null  $publicKey  clave pública armored del firmante
     *
     * @throws PgpException si la firma no puede evaluarse
     */
    public function verify(string $message, string $armoredSignature, ?string $publicKey = null): bool;
}

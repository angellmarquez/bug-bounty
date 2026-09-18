<?php

namespace App\Services\Pgp\Drivers;

use App\Services\Pgp\Contracts\PgpDriver;
use App\Services\Pgp\DataObjects\PgpKeyInfo;
use App\Services\Pgp\Exceptions\PgpDecryptionFailedException;
use App\Services\Pgp\Exceptions\PgpDriverUnavailableException;
use App\Services\Pgp\Exceptions\PgpException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Driver de respaldo para desarrollo y testing.
 *
 * NO proporciona confidencialidad real: implementa el contrato de PgpDriver de
 * forma determinista para poder ejercitar el flujo completo de la plataforma
 * sin depender de un binario de GnuPG. Su uso en producción está prohibido.
 */
class FallbackPgpDriver implements PgpDriver
{
    private const PUBLIC_HEADER = 'FAKE PGP PUBLIC KEY BLOCK';

    private const PRIVATE_HEADER = 'FAKE PGP PRIVATE KEY BLOCK';

    private const MESSAGE_HEADER = 'FAKE PGP MESSAGE';

    private const SIGNATURE_HEADER = 'FAKE PGP SIGNATURE';

    public function __construct(private readonly string $store) {}

    public function name(): string
    {
        return 'fallback';
    }

    public function available(): bool
    {
        return config('app.env', 'local') !== 'production';
    }

    /**
     * {@inheritDoc}
     */
    public function generateKeyPair(array $options = []): PgpKeyInfo
    {
        $this->assertAvailable();

        $identity = (string) ($options['identity'] ?? 'Plataforma BugBounty <seguridad@localhost>');
        $algorithm = strtolower((string) ($options['algorithm'] ?? 'ed25519'));
        $algorithm = str_starts_with($algorithm, 'rsa') ? 'RSA' : 'EdDSA';
        $bits = $algorithm === 'RSA' ? 4096 : 256;

        $creadaEn = now()->toDateTimeString();
        $expiraEn = isset($options['expires_in'])
            ? now()->add($options['expires_in'])->toDateTimeString()
            : null;

        $fingerprint = strtoupper(substr(hash('sha1', $identity.'|'.$creadaEn.'|'.Str::random(16)), 0, 40));
        $idClave = strtoupper(substr(hash('sha1', $fingerprint), 0, 16));

        $public = $this->armorBlock(self::PUBLIC_HEADER, [
            'Identidad' => $identity,
            'Algoritmo' => $algorithm,
            'Bits' => (string) $bits,
            'Huella' => $fingerprint,
            'Creada' => $creadaEn,
        ]);
        $private = $this->armorBlock(self::PRIVATE_HEADER, [
            'Identidad' => $identity,
            'Huella' => $fingerprint,
            'Contenido' => base64_encode($fingerprint.'|private'),
        ]);

        $this->ensureStore();

        File::put($this->store.'/platform.asc', $public);
        File::put($this->store.'/platform_private.asc', $private);
        File::put($this->store.'/platform.json', json_encode([
            'fingerprint' => $fingerprint,
            'id_clave' => $idClave,
            'algoritmo' => $algorithm,
            'bits' => $bits,
            'creada_en' => $creadaEn,
            'expira_en' => $expiraEn,
        ], JSON_THROW_ON_ERROR));

        return $this->platformKeyInfo();
    }

    /**
     * {@inheritDoc}
     */
    public function importKey(string $armoredPublicKey): PgpKeyInfo
    {
        $this->assertAvailable();

        $fields = $this->parseBlock($this->normalizeArmored($armoredPublicKey), self::PUBLIC_HEADER);

        if ($fields === null) {
            throw new PgpException('La cadena no parece una clave pública PGP de respaldo.');
        }

        $this->ensureStore();

        $this->importedKeys();
        File::put($this->store.'/keys/'.$fields['Huella'].'.asc', $this->normalizeArmored($armoredPublicKey));

        return $this->keyInfoFromFields($fields);
    }

    /**
     * {@inheritDoc}
     */
    public function exportPublicKey(string $fingerprint): string
    {
        $this->assertAvailable();

        $this->ensureStore();

        $fingerprint = $this->normalizeFingerprint($fingerprint);

        if (File::exists($this->store.'/keys/'.$fingerprint.'.asc')) {
            return (string) File::get($this->store.'/keys/'.$fingerprint.'.asc');
        }

        $platform = $this->platformKeyInfo();
        if ($platform !== null && $platform->fingerprint === $fingerprint) {
            return (string) File::get($this->store.'/platform.asc');
        }

        throw new PgpException("No se encontró la clave pública de respaldo con huella [{$fingerprint}].");
    }

    /**
     * {@inheritDoc}
     */
    public function exportPrivateKey(string $fingerprint): string
    {
        $this->assertAvailable();

        $this->ensureStore();

        $platform = $this->platformKeyInfo();
        if ($platform === null || $platform->fingerprint !== $this->normalizeFingerprint($fingerprint)) {
            throw new PgpException("No se encontró la clave privada de la plataforma [{$fingerprint}].");
        }

        return (string) File::get($this->store.'/platform_private.asc');
    }

    /**
     * {@inheritDoc}
     */
    public function fingerprint(string $armoredPublicKey): string
    {
        $this->assertAvailable();

        $info = $this->importKey($armoredPublicKey);

        return $info->fingerprint;
    }

    /**
     * {@inheritDoc}
     */
    public function encrypt(string $message, string $recipient): string
    {
        $this->assertAvailable();

        $recipientFingerprint = str_contains($recipient, 'FAKE PGP PUBLIC KEY')
            ? $this->fingerprint($recipient)
            : $this->normalizeFingerprint($recipient);

        if ($recipientFingerprint === '') {
            throw new PgpException('Se requiere una huella o clave pública de destinatario.');
        }

        $iv = Str::random(16);

        $raw = openssl_encrypt(
            $message,
            'aes-256-cbc',
            substr($this->secret(), 0, 32),
            OPENSSL_RAW_DATA,
            $iv,
        );

        if ($raw === false) {
            throw new PgpException('No se pudo cifrar el mensaje de respaldo.');
        }

        $payload = base64_encode($iv.$raw);

        return $this->armorBlock(self::MESSAGE_HEADER, [
            'Para' => $recipientFingerprint,
            'Cifrado' => $payload,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function decrypt(string $armoredMessage): string
    {
        $this->assertAvailable();

        $fields = $this->parseBlock($this->normalizeArmored($armoredMessage), self::MESSAGE_HEADER);

        if ($fields === null || ! isset($fields['Cifrado'])) {
            throw new PgpDecryptionFailedException('El mensaje no es un bloque de respaldo válido.');
        }

        $payload = base64_decode($fields['Cifrado'], true);
        if ($payload === false || strlen($payload) < 17) {
            throw new PgpDecryptionFailedException('El mensaje está corrupto.');
        }

        $iv = substr($payload, 0, 16);
        $ciphertext = substr($payload, 16);

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', substr($this->secret(), 0, 32), OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new PgpDecryptionFailedException('No se pudo descifrar el mensaje de respaldo.');
        }

        return $plaintext;
    }

    /**
     * {@inheritDoc}
     */
    public function sign(string $message, ?string $privateKey = null): string
    {
        $this->assertAvailable();

        $digest = hash_hmac('sha256', $message, $this->secret());

        return $this->armorBlock(self::SIGNATURE_HEADER, [
            'Firma' => base64_encode($digest),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function verify(string $message, string $armoredSignature, ?string $publicKey = null): bool
    {
        $this->assertAvailable();

        $fields = $this->parseBlock($this->normalizeArmored($armoredSignature), self::SIGNATURE_HEADER);

        if ($fields === null || ! isset($fields['Firma'])) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $message, $this->secret()));

        return hash_equals($fields['Firma'], $expected);
    }

    /**
     * Formatea un bloque armor "fake" con una cabecera de comentario.
     *
     * @param  array<string, string>  $lines
     */
    private function armorBlock(string $header, array $lines): string
    {
        $body = collect($lines)->map(fn (string $value, string $key) => "{$key}: {$value}")->implode("\n");

        return "-----BEGIN {$header}-----\n"
            .'Comment: DRIVER DE RESPALDO - NO ES PGP REAL (solo local/testing)'."\n"
            .$body."\n"
            ."-----END {$header}-----";
    }

    /**
     * Analiza un bloque armor "fake" y devuelve sus campos.
     *
     * @return array<string, string>|null
     */
    private function parseBlock(string $armored, string $header): ?array
    {
        if (! str_contains($armored, "BEGIN {$header}") || ! str_contains($armored, "END {$header}")) {
            return null;
        }

        $fields = [];

        foreach (explode("\n", $armored) as $line) {
            if (str_contains($line, ': ')) {
                [$key, $value] = explode(': ', $line, 2);
                $fields[$key] = trim($value);
            }
        }

        return $fields === [] ? null : $fields;
    }

    private function secret(): string
    {
        return hash('sha256', (string) config('app.key').'|pgp-fallback');
    }

    private function platformKeyInfo(): ?PgpKeyInfo
    {
        $this->ensureStore();

        $path = $this->store.'/platform.json';

        if (! File::exists($path)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return PgpKeyInfo::fromArray($data);
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function keyInfoFromFields(array $fields): PgpKeyInfo
    {
        return new PgpKeyInfo(
            fingerprint: strtoupper($fields['Huella'] ?? ''),
            idClave: isset($fields['Huella']) ? strtoupper(substr(hash('sha1', $fields['Huella']), 0, 16)) : null,
            algoritmo: $fields['Algoritmo'] ?? null,
            bits: isset($fields['Bits']) ? (int) $fields['Bits'] : null,
            creadaEn: isset($fields['Creada']) ? now()->parse($fields['Creada']) : null,
            expiraEn: isset($fields['Expira']) ? now()->parse($fields['Expira']) : null,
        );
    }

    /**
     * @return array<int, string>
     */
    private function importedKeys(): array
    {
        $this->ensureStore();

        $keys = File::exists($this->store.'/keys') ? File::files($this->store.'/keys') : [];

        return array_map(fn ($file) => basename($file->getPathname(), '.asc'), $keys);
    }

    private function ensureStore(): void
    {
        if (! File::isDirectory($this->store)) {
            File::makeDirectory($this->store, 0700, true);
        }

        if (! File::isDirectory($this->store.'/keys')) {
            File::makeDirectory($this->store.'/keys', 0700, true);
        }
    }

    private function normalizeArmored(string $raw): string
    {
        return trim((string) preg_replace('/\r\n/', "\n", $raw));
    }

    private function normalizeFingerprint(string $fingerprint): string
    {
        return strtoupper((string) preg_replace('/[^A-Fa-f0-9]/', '', $fingerprint));
    }

    private function assertAvailable(): void
    {
        if (! $this->available()) {
            throw new PgpDriverUnavailableException(
                'El driver de respaldo PGP está prohibido en producción.',
            );
        }
    }
}

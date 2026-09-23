<?php

namespace App\Services\Pgp\Drivers;

use App\Services\Pgp\Contracts\PgpDriver;
use App\Services\Pgp\DataObjects\PgpKeyInfo;
use App\Services\Pgp\Exceptions\PgpDecryptionFailedException;
use App\Services\Pgp\Exceptions\PgpDriverUnavailableException;
use App\Services\Pgp\Exceptions\PgpException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Driver PGP sobre el binario de GnuPG (Gpg4win en Windows, gpg en Linux).
 *
 * Aísla cada keyring en un homedir propio y opera siempre en modo no
 * interactivo (--batch, --pinentry-mode loopback).
 */
class GpgBinaryDriver implements PgpDriver
{
    public function __construct(
        private readonly string $binary,
        private readonly string $homedir,
        private readonly string $passphrase = '',
        private readonly int $timeout = 120,
    ) {}

    public function name(): string
    {
        return 'gpg';
    }

    public function available(): bool
    {
        try {
            return $this->runShell($this->binary, '--version')->getExitCode() === 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Alias de homedir de contactos (claves públicas importadas).
     */
    protected function contactsHome(): string
    {
        return $this->homedir.'-contacts';
    }

    /**
     * {@inheritDoc}
     */
    public function generateKeyPair(array $options = []): PgpKeyInfo
    {
        $this->assertAvailable();

        $identity = (string) ($options['identity'] ?? 'Plataforma BugBounty <seguridad@localhost>');
        $algorithm = (string) ($options['algorithm'] ?? 'ed25519');
        $expiresIn = (string) ($options['expires_in'] ?? '1y');
        $algorithm = in_array(strtolower($algorithm), ['ed25519', 'rsa4096', 'rsa3072', 'rsa2048'], true)
            ? strtolower($algorithm)
            : 'ed25519';

        // El llavero puede tener ya otras claves: la nueva es la que aparece después de generarla.
        $antes = array_map(fn (PgpKeyInfo $clave) => $clave->fingerprint, $this->primaryKeys($this->homedir));

        $this->run([
            '--quick-generate-key', $identity, $algorithm, 'sign', $expiresIn,
        ], '', $this->homedir);

        $generated = self::claveNueva($antes, $this->primaryKeys($this->homedir));
        if ($generated === null) {
            throw new PgpException('No se pudo generar el par de claves PGP.');
        }

        $this->run([
            '--quick-add-key', $generated->fingerprint, $this->encryptionAlgorithm($algorithm), 'encr', $expiresIn,
        ], '', $this->homedir);

        $this->rememberKeyInRing($this->homedir, $generated->fingerprint);

        return $generated;
    }

    /**
     * {@inheritDoc}
     */
    public function importKey(string $armoredPublicKey): PgpKeyInfo
    {
        $this->assertAvailable();

        $armoredPublicKey = $this->normalizeArmored($armoredPublicKey);

        $this->run(['--import'], $armoredPublicKey, $this->contactsHome());

        $info = $this->primaryKeyInfo($this->contactsHome());
        if ($info === null) {
            throw new PgpException('No se pudo importar la clave pública PGP.');
        }

        $this->rememberKeyInRing($this->contactsHome(), $info->fingerprint);

        return $info;
    }

    /**
     * {@inheritDoc}
     *
     * La exportación de una clave secreta (--export-secret-keys) trae también
     * los paquetes de la clave pública, así que un solo --import al homedir
     * reconstruye el par completo (sirve tanto para firmar/descifrar como
     * para cifrar hacia la propia plataforma).
     */
    public function importPrivateKey(string $armoredPrivateKey): PgpKeyInfo
    {
        $this->assertAvailable();

        $this->run(['--import'], $this->normalizeArmored($armoredPrivateKey), $this->homedir);

        $info = $this->primaryKeyInfo($this->homedir);
        if ($info === null) {
            throw new PgpException('No se pudo importar la clave privada PGP.');
        }

        $this->rememberKeyInRing($this->homedir, $info->fingerprint);

        // A diferencia de una clave generada en el propio keyring (confianza
        // "ultimate" automática), una recién importada queda con confianza
        // desconocida y --encrypt la rechaza ("no hay seguridad de que esta
        // clave pertenezca..."). Es la propia clave de la plataforma (tenemos
        // su secreta): la marcamos de confianza última a mano.
        $this->run(['--import-ownertrust'], "{$info->fingerprint}:6:\n", $this->homedir);

        return $info;
    }

    /**
     * {@inheritDoc}
     */
    public function exportPublicKey(string $fingerprint): string
    {
        $this->assertAvailable();

        $fingerprint = $this->normalizeFingerprint($fingerprint);

        foreach ([$this->homedir, $this->contactsHome()] as $dir) {
            if (! $this->ringHasKey($dir, $fingerprint)) {
                continue;
            }

            $result = $this->run(['--armor', '--export', $fingerprint], '', $dir);

            if ($result['exitCode'] === 0 && trim($result['output']) !== '') {
                return $this->normalizeArmored($result['output']);
            }
        }

        throw new PgpException("No se encontró la clave pública con huella [{$fingerprint}].");
    }

    /**
     * {@inheritDoc}
     */
    public function exportPrivateKey(string $fingerprint): string
    {
        $this->assertAvailable();

        $fingerprint = $this->normalizeFingerprint($fingerprint);

        if (! $this->ringHasKey($this->homedir, $fingerprint)) {
            throw new PgpException("No se encontró la clave privada de la plataforma [{$fingerprint}].");
        }

        $result = $this->run(['--armor', '--export-secret-keys', $fingerprint], '', $this->homedir);

        if (trim($result['output']) === '') {
            throw new PgpException('gpg no devolvió la clave privada.');
        }

        return $this->normalizeArmored($result['output']);
    }

    /**
     * {@inheritDoc}
     */
    public function fingerprint(string $armoredPublicKey): string
    {
        $this->assertAvailable();

        $result = $this->run(['--import'], $this->normalizeArmored($armoredPublicKey), $this->contactsHome());

        if ($result['exitCode'] !== 0) {
            throw new PgpException('No se pudo importar la clave pública PGP.');
        }

        $info = $this->primaryKeyInfo($this->contactsHome());
        if ($info === null) {
            throw new PgpException('No se pudo calcular la huella de la clave pública PGP.');
        }

        return $info->fingerprint;
    }

    /**
     * {@inheritDoc}
     *
     * Todos los destinatarios tienen que resolverse en el **mismo** homedir
     * (gpg no puede leer dos llaveros a la vez en una sola invocación): se
     * importa cada clave pública que haga falta a `contactsHome()` antes de
     * cifrar, así un solo mensaje queda cifrado para todos a la vez.
     */
    public function encrypt(string $message, string|array $recipients): string
    {
        $this->assertAvailable();

        $recipientIds = array_map(fn (string $recipient): string => $this->resolveRecipientId($recipient), (array) $recipients);

        if ($recipientIds === []) {
            throw new PgpException('Se requiere al menos un destinatario para cifrar.');
        }

        $dir = $this->contactsHome();
        // Las públicas importadas a contactsHome() quedan con confianza "desconocida" y gpg se
        // niega a cifrarles. Los destinatarios son siempre huellas de claves que genera y
        // custodia la propia plataforma (nunca las elige el usuario): se confía en ellas.
        $args = ['--armor', '--encrypt', '--trust-model', 'always'];

        foreach ($recipientIds as $recipientId) {
            $args[] = '--recipient';
            $args[] = $recipientId;
        }

        $args[] = '--output';
        $args[] = '-';

        $result = $this->run($args, $message, $dir);

        if (trim($result['output']) === '') {
            throw new PgpException('gpg no devolvió mensaje cifrado.');
        }

        return $this->normalizeArmored($result['output']);
    }

    /**
     * Resuelve un destinatario (huella o clave pública armored) a una huella
     * que ya está importada en `contactsHome()`, listo para usarse en `--recipient`.
     */
    private function resolveRecipientId(string $recipient): string
    {
        if (str_contains($recipient, 'BEGIN PGP')) {
            return $this->importKey($recipient)->fingerprint;
        }

        $recipientId = $this->normalizeFingerprint($recipient);

        if ($this->ringHasKey($this->contactsHome(), $recipientId)) {
            return $recipientId;
        }

        // La propia clave del homedir (custodia/empresa que generamos acá) también
        // sirve como destinatario: se re-exporta su pública a contactsHome().
        if ($this->ringHasKey($this->homedir, $recipientId)) {
            $this->run(['--import'], $this->exportPublicKey($recipientId), $this->contactsHome());
            $this->rememberKeyInRing($this->contactsHome(), $recipientId);

            return $recipientId;
        }

        throw new PgpException("No se encontró una clave pública local para la huella [{$recipientId}].");
    }

    /**
     * {@inheritDoc}
     */
    public function decrypt(string $armoredMessage): string
    {
        $this->assertAvailable();

        // run() lanza PgpException genérica si gpg sale con error: aquí se traduce a la
        // específica de descifrado, que es la que distingue "no se pudo leer" de "gpg no anda".
        try {
            $result = $this->run(['--decrypt', '--output', '-'], $this->normalizeArmored($armoredMessage), $this->homedir);
        } catch (PgpException $e) {
            throw new PgpDecryptionFailedException('No se pudo descifrar el mensaje PGP.', 0, $e);
        }

        if ($result['exitCode'] !== 0) {
            throw new PgpDecryptionFailedException('No se pudo descifrar el mensaje PGP.');
        }

        return $result['output'];
    }

    /**
     * {@inheritDoc}
     */
    public function sign(string $message, ?string $privateKey = null): string
    {
        $this->assertAvailable();

        $dir = $this->homedir;
        $localUser = null;

        if ($privateKey !== null) {
            $dir = $this->ephemeralHomedir();
            $this->run(['--import'], $this->normalizeArmored($privateKey), $dir);
            $stored = $this->primaryKeyInfo($dir);

            if ($stored === null) {
                throw new PgpException('No se pudo importar la clave privada para firmar.');
            }

            $localUser = $stored->fingerprint;
        }

        $args = ['--armor', '--detach-sign'];

        if ($localUser !== null) {
            $args[] = '--local-user';
            $args[] = $localUser;
        }

        $args[] = '--output';
        $args[] = '-';

        $result = $this->run($args, $message, $dir);

        if (trim($result['output']) === '' || str_contains($result['error'], 'no default secret key')) {
            throw new PgpException('No se pudo firmar el mensaje. Asegúrate de que la clave privada de la plataforma está importada.');
        }

        return $this->normalizeArmored($result['output']);
    }

    /**
     * {@inheritDoc}
     */
    public function verify(string $message, string $armoredSignature, ?string $publicKey = null): bool
    {
        $this->assertAvailable();

        $dir = $this->homedir;

        if ($publicKey !== null) {
            $dir = $this->ephemeralHomedir();
            $this->run(['--import'], $this->normalizeArmored($publicKey), $dir);
        }

        $messageFile = tempnam(sys_get_temp_dir(), 'pgp_msj');
        $signatureFile = tempnam(sys_get_temp_dir(), 'pgp_sig');

        if ($messageFile === false || $signatureFile === false) {
            throw new PgpException('No se pudo crear archivos temporales para verificar la firma.');
        }

        try {
            file_put_contents($messageFile, $message);
            file_put_contents($signatureFile, $this->normalizeArmored($armoredSignature));

            $result = $this->run(['--verify', $signatureFile, $messageFile], '', $dir);
        } finally {
            @unlink($messageFile);
            @unlink($signatureFile);
            @unlink(dirname($messageFile).'/'.$this->signatureName($signatureFile));
        }

        return $result['exitCode'] === 0;
    }

    /**
     * Ejecuta el binario con argumentos base aislados del homedir.
     *
     * @param  array<int, string>  $arguments
     * @return array{exitCode: int, output: string, error: string}
     */
    private function run(array $arguments, string $input, string $homedir): array
    {
        $this->ensureHomedir($homedir);

        // El primer gpg-agent que arranca en un homedir crea su socket; si dos
        // procesos gpg piden uno a la vez (p. ej. dos campos cifrados del mismo
        // request), el segundo puede pisar la creación del socket y fallar con
        // "no se puede crear el socket" / "can't connect to the agent". Es una
        // carrera transitoria de arranque, no un fallo real: se reintenta unas
        // pocas veces con una pausa corta antes de darlo por perdido.
        $intentos = 3;

        for ($intento = 1; $intento <= $intentos; $intento++) {
            $process = $this->gpgProcess([...$this->baseArgs($homedir), ...$arguments]);
            $process->setTimeout($this->timeout);
            $process->setInput($input);

            try {
                $process->run();
            } catch (Throwable $e) {
                throw new PgpException("No se pudo ejecutar el binario de GnuPG [{$this->binary}]: {$e->getMessage()}", 0, $e);
            }

            $output = $process->getOutput();
            $error = $process->getErrorOutput();

            if ($process->getExitCode() === 0) {
                return [
                    'exitCode' => $process->getExitCode(),
                    'output' => $output,
                    'error' => $error,
                ];
            }

            $esCarreraDeAgente = str_contains($error, 'gpg-agent') && (
                str_contains($error, 'no se puede crear el socket')
                || str_contains($error, "can't connect")
                || str_contains($error, 'failed to start')
            );

            if (! $esCarreraDeAgente || $intento === $intentos) {
                throw new PgpException(
                    sprintf(
                        'gpg falló con código %d: %s',
                        $process->getExitCode() ?? -1,
                        Str::limit(trim($error) !== '' ? $error : $output, 500),
                    ),
                );
            }

            usleep(300_000 * $intento);
        }

        throw new PgpException('gpg falló: no se pudo conectar con gpg-agent tras varios intentos.');
    }

    /**
     * Ejecuta un comando simple para comprobar la disponibilidad del binario.
     */
    private function runShell(string $binary, string $argument): Process
    {
        $process = new Process([$binary, $argument]);
        $process->setTimeout(10);
        $process->run();

        return $process;
    }

    private bool $availableConfirmed = false;

    /** @var array<string, bool> */
    private array $ensuredHomedirs = [];

    /** @var array<string, array<string, true>> */
    private array $ringKeysCache = [];

    /**
     * Argumentos base (homedir + modo no interactivo).
     *
     * @return array<int, string>
     */
    protected function baseArgs(string $homedir): array
    {
        return [
            $this->binary,
            '--homedir', $homedir,
            '--batch',
            '--no-tty',
            '--pinentry-mode', 'loopback',
            '--passphrase', $this->passphrase,
        ];
    }

    /**
     * Crea el proceso de gpg con la raíz del proyecto como directorio de trabajo.
     *
     * El homedir suele configurarse relativo (storage/app/pgp/gpg) y algunas builds de
     * gpg en Windows (MSYS) no entienden rutas absolutas "C:\...". Fijar el cwd evita que
     * el llavero cambie de sitio según cómo se sirva la app (`artisan serve`/nginx usan public/).
     *
     * @param  array<int, string>  $command
     */
    private function gpgProcess(array $command): Process
    {
        return new Process($command, function_exists('base_path') ? base_path() : null);
    }

    private function ensureHomedir(string $dir): void
    {
        if (isset($this->ensuredHomedirs[$dir])) {
            return;
        }

        if (! preg_match('#^(?:[A-Za-z]:)?[\\\\/]#', $dir) && function_exists('base_path')) {
            $dir = base_path($dir);
        }

        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException("No se pudo crear el homedir de GnuPG [{$dir}].");
        }

        $this->ensureAgentConf($dir);
        $this->ensuredHomedirs[$dir] = true;
    }

    /**
     * gpg-agent, por defecto, intenta arrancar scdaemon (soporte de tarjetas
     * inteligentes) al recibir la primera petición. En Windows esa detección
     * puede colgarse varios segundos (o fallar al crear el socket) en máquinas
     * sin lector, que es la inmensa mayoría de los entornos de desarrollo.
     * Se desactiva de una para que el agente arranque rápido y confiable.
     */
    private function ensureAgentConf(string $dir): void
    {
        $conf = $dir.DIRECTORY_SEPARATOR.'gpg-agent.conf';

        $requeridas = ['disable-scdaemon', 'allow-loopback-pinentry'];
        $actuales = is_file($conf) ? (array) file($conf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        $faltantes = array_diff($requeridas, array_map(fn (string|false $line): string => trim((string) $line), $actuales));

        if ($faltantes === []) {
            return;
        }

        file_put_contents($conf, implode("\n", [...$actuales, ...$faltantes])."\n");
    }

    private function ringHasKey(string $dir, string $fingerprint): bool
    {
        // Solo se cachean los positivos: una clave ausente puede importarse después
        // (p. ej. fingerprint()) y un negativo cacheado la volvería invisible.
        if (isset($this->ringKeysCache[$dir][$fingerprint])) {
            return true;
        }

        $this->ensureHomedir($dir);

        $process = $this->gpgProcess([...$this->baseArgs($dir), '--list-keys', $fingerprint]);
        $process->setTimeout($this->timeout);

        try {
            $process->run();

            if ($process->getExitCode() !== 0) {
                return false;
            }

            $this->rememberKeyInRing($dir, $fingerprint);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function rememberKeyInRing(string $dir, string $fingerprint): void
    {
        $this->ringKeysCache[$dir][$fingerprint] = true;
    }

    /**
     * Devuelve la información de la primera clave primaria del anillo.
     */
    private function primaryKeyInfo(string $dir): ?PgpKeyInfo
    {
        return $this->primaryKeys($dir)[0] ?? null;
    }

    /**
     * Todas las claves primarias del anillo, en el orden en que gpg las lista.
     *
     * @return list<PgpKeyInfo>
     */
    private function primaryKeys(string $dir): array
    {
        try {
            $process = $this->gpgProcess([...$this->baseArgs($dir), '--with-colons', '--with-fingerprint', '--list-keys']);
            $process->setTimeout($this->timeout);
            $process->run();
        } catch (Throwable) {
            return [];
        }

        if ($process->getExitCode() !== 0) {
            return [];
        }

        return self::parsearListado($process->getOutput());
    }

    /**
     * Interpreta la salida `--with-colons --list-keys`: cada línea `pub` va seguida de la `fpr`
     * de su clave primaria (las subclaves llevan sus propias `sub`/`fpr`, que se ignoran).
     *
     * @return list<PgpKeyInfo>
     */
    public static function parsearListado(string $salida): array
    {
        $claves = [];
        $publica = null;

        foreach (array_filter(explode("\n", $salida), fn (string $linea) => $linea !== '') as $linea) {
            $campos = explode(':', $linea);

            if ($campos[0] === 'pub') {
                $publica = $campos;

                continue;
            }

            if ($campos[0] === 'fpr' && $publica !== null && ($campos[9] ?? '') !== '') {
                $claves[] = new PgpKeyInfo(
                    fingerprint: strtoupper($campos[9]),
                    idClave: strtoupper($publica[4] ?? ''),
                    algoritmo: self::algoritmoDe($publica[3] ?? null),
                    bits: isset($publica[2]) && is_numeric($publica[2]) ? (int) $publica[2] : null,
                    creadaEn: isset($publica[5]) && is_numeric($publica[5])
                        ? Carbon::createFromTimestamp((int) $publica[5])
                        : null,
                    expiraEn: isset($publica[6]) && is_numeric($publica[6]) && (int) $publica[6] > 0
                        ? Carbon::createFromTimestamp((int) $publica[6])
                        : null,
                );

                // Las `fpr` que siguen son de subclaves, hasta la próxima `pub`.
                $publica = null;
            }
        }

        return $claves;
    }

    /**
     * La clave que apareció en el llavero respecto a `$antes` (huellas previas); null si no hay
     * exactamente una nueva.
     *
     * @param  list<string>  $antes
     * @param  list<PgpKeyInfo>  $despues
     */
    public static function claveNueva(array $antes, array $despues): ?PgpKeyInfo
    {
        $nuevas = array_values(array_filter($despues, fn (PgpKeyInfo $clave) => ! in_array($clave->fingerprint, $antes, true)));

        return count($nuevas) === 1 ? $nuevas[0] : null;
    }

    private function normalizeArmored(string $raw): string
    {
        return trim((string) preg_replace('/\r\n/', "\n", $raw));
    }

    private function normalizeFingerprint(string $fingerprint): string
    {
        return strtoupper((string) preg_replace('/[^A-Fa-f0-9]/', '', $fingerprint));
    }

    private function encryptionAlgorithm(string $algorithm): string
    {
        return str_starts_with($algorithm, 'rsa') ? 'rsa4096' : 'cv25519';
    }

    private function signatureName(string $file): string
    {
        return basename($file);
    }

    private static function algoritmoDe(?string $number): ?string
    {
        return match ($number) {
            '1', '2', '3' => 'RSA',
            '9' => 'ElGamal',
            '17' => 'DSA',
            '22' => 'EdDSA',
            default => null,
        };
    }

    private function ephemeralHomedir(): string
    {
        return sys_get_temp_dir().'/pgp_ephemeral_'.Str::random(16);
    }

    private function assertAvailable(): void
    {
        // Solo se cachea el éxito: lanzar "gpg --version" en cada operación duplicaba
        // los procesos por cifrado/descifrado (caro en Windows).
        if ($this->availableConfirmed) {
            return;
        }

        if (! $this->available()) {
            throw new PgpDriverUnavailableException(sprintf('El binario de GnuPG [%s] no está disponible.', $this->binary));
        }

        $this->availableConfirmed = true;
    }
}

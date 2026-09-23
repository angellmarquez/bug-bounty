<?php

use App\Models\ClavePgpPlataforma;
use App\Services\Pgp\Contracts\PgpDriver;
use App\Services\Pgp\Drivers\FallbackPgpDriver;
use App\Services\Pgp\Drivers\GpgBinaryDriver;
use App\Services\Pgp\Exceptions\PgpDecryptionFailedException;
use App\Services\Pgp\Exceptions\PgpException;
use App\Services\Pgp\PgpService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Este archivo prueba el contrato del driver de respaldo (sigue existiendo para entornos sin
// GnuPG): se fuerza aquí, porque el resto del suite usa GnuPG real. Los tests con el binario
// real construyen su GpgBinaryDriver explícitamente, y ClavesPorEmpresaTest cubre el flujo real.
beforeEach(function () {
    $this->storePgp = sys_get_temp_dir().'/pgp_test_'.uniqid();
    config(['pgp.driver' => 'fallback']);
    config(['pgp.fallback.store' => $this->storePgp]);
    config(['pgp.identity' => 'Plataforma de Prueba <pruebas@localhost>']);

    $this->app->forgetInstance(PgpDriver::class);
    $this->app->forgetInstance(PgpService::class);
});

afterEach(function () {
    if (is_dir($this->storePgp)) {
        (new Filesystem)->deleteDirectory($this->storePgp);
    }
});

function pgpService(): PgpService
{
    return app(PgpService::class);
}

test('con PGP_DRIVER=fallback se resuelve el driver de respaldo', function () {
    expect(app(PgpDriver::class))
        ->toBeInstanceOf(FallbackPgpDriver::class)
        ->available()->toBeTrue();
});

test('el driver de respaldo está prohibido en producción', function () {
    $driver = new FallbackPgpDriver($this->storePgp);

    config(['app.env' => 'production']);

    expect($driver->available())->toBeFalse();

    try {
        $driver->encrypt('secreto', str_repeat('A', 40));
        test()->fail('Debería lanzar PgpException en producción.');
    } catch (PgpException $e) {
        expect($e)->toBeInstanceOf(PgpException::class);
    } finally {
        config(['app.env' => 'testing']);
    }
});

test('el driver binario detecta su disponibilidad sin lanzar errores', function () {
    $driver = new GpgBinaryDriver('gpg-no-existe-xyz', $this->storePgp);

    expect($driver->available())->toBeBool();
});

test('genera el par de claves de la plataforma y lo persiste activo', function () {
    $clave = pgpService()->generatePlatformKeyPair();

    expect($clave)->toBeInstanceOf(ClavePgpPlataforma::class)
        ->and($clave->huella)->toMatch('/^[A-F0-9]{40}$/')
        ->and($clave->clave_publica)->toContain('FAKE PGP PUBLIC KEY BLOCK')
        ->and($clave->clave_privada)->toContain('FAKE PGP PRIVATE KEY BLOCK')
        ->and($clave->activa)->toBeTrue()
        ->and(pgpService()->platformKey()->is($clave))->toBeTrue();
});

test('regenerar la plataforma desactiva las claves antiguas', function () {
    $primera = pgpService()->generatePlatformKeyPair();
    $segunda = pgpService()->generatePlatformKeyPair();

    expect($primera->fresh()->activa)->toBeFalse()
        ->and($segunda->fresh()->activa)->toBeTrue()
        ->and(ClavePgpPlataforma::query()->where('activa', true)->count())->toBe(1);
});

test('la clave privada se persiste cifrada en reposo', function () {
    $clave = pgpService()->generatePlatformKeyPair();

    $crudo = DB::table('claves_pgp_plataforma')->where('id', $clave->id)->value('clave_privada');

    expect($crudo)->not->toContain('FAKE PGP PRIVATE KEY BLOCK')
        ->and($clave->clave_privada)->toContain('FAKE PGP PRIVATE KEY BLOCK');
});

test('cifra y descifra un mensaje con la clave de la plataforma', function () {
    $clave = pgpService()->generatePlatformKeyPair();

    $cifrado = pgpService()->encrypt('contenido-confidencial', $clave->huella);
    $descifrado = pgpService()->decrypt($cifrado);

    expect($cifrado)->toContain('BEGIN FAKE PGP MESSAGE')
        ->and($descifrado)->toBe('contenido-confidencial')
        ->and($cifrado)->not->toContain('contenido-confidencial');
});

test('cifra para una clave pública armor importada', function () {
    $destinatario = pgpService()->generatePlatformKeyPair()->clave_publica;

    $cifrado = pgpService()->encrypt('para-el-investigador', $destinatario);

    expect($cifrado)->toContain('BEGIN FAKE PGP MESSAGE');
});

test('importa claves públicas y calcula huellas estables', function () {
    $clave = pgpService()->generatePlatformKeyPair();

    $info = pgpService()->importPublicKey($clave->clave_publica);

    expect($info->fingerprint)->toBe($clave->huella)
        ->and(pgpService()->fingerprint($clave->clave_publica))->toBe($clave->huella)
        ->and(pgpService()->usesFallback())->toBeTrue();
});

test('firma y verifica un mensaje', function () {
    $clave = pgpService()->generatePlatformKeyPair();

    $firma = pgpService()->sign('mensaje-firmado');

    expect($firma)->toContain('BEGIN FAKE PGP SIGNATURE')
        ->and(pgpService()->verify('mensaje-firmado', $firma, $clave->clave_publica))->toBeTrue()
        ->and(pgpService()->verify('mensaje-trocado', $firma, $clave->clave_publica))->toBeFalse();
});

test('falla al descifrar un mensaje corrupto', function () {
    pgpService()->generatePlatformKeyPair();

    pgpService()->decrypt('-----BEGIN FAKE PGP MESSAGE-----'."\n".'Cifrado: basura'."\n".'-----END FAKE PGP MESSAGE-----');
})->throws(PgpDecryptionFailedException::class);

test('pgp:setup genera la clave y es idempotente', function () {
    expect(Artisan::call('pgp:setup'))->toBe(0)
        ->and(ClavePgpPlataforma::query()->count())->toBe(1);

    expect(Artisan::call('pgp:setup'))->toBe(0)
        ->and(ClavePgpPlataforma::query()->count())->toBe(1);
});

test('pgp:setup --force regenera la clave', function () {
    $primera = pgpService()->generatePlatformKeyPair();

    expect(Artisan::call('pgp:setup --force'))->toBe(0)
        ->and(ClavePgpPlataforma::query()->where('activa', true)->first()->huella)->not->toBe($primera->huella);
});

test('pgp:check pasa cuando la infraestructura está operativa', function () {
    pgpService()->generatePlatformKeyPair();

    expect(Artisan::call('pgp:check'))->toBe(0);
});

test('pgp:check falla cuando no hay clave de plataforma', function () {
    expect(Artisan::call('pgp:check'))->toBe(1);
});

test('restaurarEnKeyring reimporta la clave activa en un keyring nuevo', function () {
    $clave = pgpService()->generatePlatformKeyPair();

    // Simula un disco efímero: el keyring local desaparece, la fila en BD no.
    (new Filesystem)->deleteDirectory($this->storePgp);
    expect(is_dir($this->storePgp))->toBeFalse();

    expect(pgpService()->restaurarEnKeyring())->toBeTrue();

    $cifrado = pgpService()->encrypt('tras-restaurar', $clave->huella);
    expect(pgpService()->decrypt($cifrado))->toBe('tras-restaurar');
});

test('restaurarEnKeyring no hace nada si no hay clave activa', function () {
    expect(pgpService()->restaurarEnKeyring())->toBeFalse();
});

test('pgp:restore reimporta la clave y es un no-op sin clave activa', function () {
    expect(Artisan::call('pgp:restore'))->toBe(0)
        ->and(Artisan::output())->toContain('nada que restaurar');

    pgpService()->generatePlatformKeyPair();
    (new Filesystem)->deleteDirectory($this->storePgp);

    expect(Artisan::call('pgp:restore'))->toBe(0)
        ->and(Artisan::output())->toContain('reimportada');
});

test('el driver binario reconstruye su keyring real desde una clave exportada', function () {
    $binario = (string) config('pgp.gpg.binary', 'gpg');
    $driver = new GpgBinaryDriver($binario, sys_get_temp_dir().'/pgp_gpg_test_'.uniqid());

    if (! $driver->available()) {
        test()->markTestSkipped('No hay binario de GnuPG disponible en este entorno.');
    }

    $info = $driver->generateKeyPair(['identity' => 'Prueba Restore <restore@localhost>', 'expires_in' => '1d']);
    $privada = $driver->exportPrivateKey($info->fingerprint);

    // Un keyring "nuevo" (homedir distinto) simula el disco efímero.
    $driverNuevo = new GpgBinaryDriver($binario, sys_get_temp_dir().'/pgp_gpg_test_'.uniqid());
    $restaurada = $driverNuevo->importPrivateKey($privada);

    expect($restaurada->fingerprint)->toBe($info->fingerprint);

    $cifrado = $driverNuevo->encrypt('mensaje-real', $info->fingerprint);
    expect($driverNuevo->decrypt($cifrado))->toBe('mensaje-real');
});

test('la clave de plataforma se puede crear por factory', function () {
    $activa = ClavePgpPlataforma::factory()->create();
    $inactiva = ClavePgpPlataforma::factory()->inactiva()->create();

    expect($activa->activa)->toBeTrue()
        ->and($inactiva->activa)->toBeFalse()
        ->and($inactiva->huella)->not->toBe($activa->huella);
});

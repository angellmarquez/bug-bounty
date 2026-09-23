<?php

use App\Models\Auditoria;
use App\Models\ClavePgpEmpresa;
use App\Models\Empresa;
use App\Models\Reporte;
use App\Services\Pgp\Drivers\GpgBinaryDriver;
use App\Services\Pgp\Exceptions\PgpException;
use App\Services\Pgp\PgpService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Llaveros creados en el test en curso, para cerrar su gpg-agent y borrarlos al terminar.
 *
 * @return array<int, string>
 */
function llaverosAislados(?string $nuevo = null, bool $vaciar = false): array
{
    static $llaveros = [];

    if ($nuevo !== null) {
        $llaveros[] = $nuevo;
    }

    $actuales = $llaveros;

    if ($vaciar) {
        $llaveros = [];
    }

    return $actuales;
}

/**
 * Un llavero nuevo y vacío con UNA sola clave privada importada: si descifra,
 * es porque el mensaje estaba cifrado a esa clave, no porque el llavero
 * compartido de la app tuviera todas.
 */
function llaveroSoloCon(string $clavePrivada): GpgBinaryDriver
{
    $homedir = 'storage/app/pgp/gpg-testing-aislado-'.Str::lower(Str::random(8));
    llaverosAislados($homedir);

    $driver = new GpgBinaryDriver(
        binary: (string) config('pgp.gpg.binary'),
        homedir: $homedir,
        passphrase: (string) config('pgp.gpg.passphrase'),
        timeout: 30,
    );
    $driver->importPrivateKey($clavePrivada);

    return $driver;
}

beforeEach(function () {
    if (app(PgpService::class)->usesFallback()) {
        $this->markTestSkipped('Requiere GnuPG real: el doble de pruebas no cifra por destinatario.');
    }
});

// Cada llavero arranca su propio gpg-agent: sin esto quedan procesos colgados y carpetas sueltas.
afterEach(function () {
    $directorio = dirname((string) config('pgp.gpg.binary'));
    $nombre = PHP_OS_FAMILY === 'Windows' ? 'gpgconf.exe' : 'gpgconf';
    $gpgconf = $directorio === '.' ? $nombre : $directorio.DIRECTORY_SEPARATOR.$nombre;

    foreach (llaverosAislados(vaciar: true) as $homedir) {
        $ruta = base_path($homedir);
        (new Process([$gpgconf, '--homedir', $ruta, '--kill', 'gpg-agent']))->run();
        File::deleteDirectory($ruta);
    }
});

test('cada empresa recibe su propia clave, creada sola la primera vez', function () {
    $pgp = app(PgpService::class);
    $empresa = Empresa::factory()->aprobada()->create();

    $clave = $pgp->claveDeEmpresa($empresa);

    expect($clave->empresa_id)->toBe($empresa->id)
        ->and($pgp->claveDeEmpresa($empresa)->id)->toBe($clave->id)
        ->and(ClavePgpEmpresa::count())->toBe(1)
        ->and(Auditoria::where('accion', 'pgp.clave_empresa_generada')->exists())->toBeTrue();
});

test('un reporte queda cifrado a la empresa y a la custodia: cada una lo abre por separado', function () {
    $pgp = app(PgpService::class);
    $empresa = Empresa::factory()->aprobada()->create();

    $cifrado = $pgp->cifrarReporte('XSS en el login', ['pasos' => 'abrir /login'], $empresa);

    $soloEmpresa = llaveroSoloCon($pgp->claveDeEmpresa($empresa)->clave_privada);
    $soloCustodia = llaveroSoloCon($pgp->platformKey()->clave_privada);

    expect($soloEmpresa->decrypt($cifrado['descripcion']))->toBe('XSS en el login')
        ->and($soloCustodia->decrypt($cifrado['descripcion']))->toBe('XSS en el login');
});

test('una empresa no puede descifrar lo cifrado para otra empresa', function () {
    $pgp = app(PgpService::class);
    $empresaA = Empresa::factory()->aprobada()->create();
    $empresaB = Empresa::factory()->aprobada()->create();

    $cifradoParaA = $pgp->cifrarReporte('Secreto de A', [], $empresaA);
    $soloB = llaveroSoloCon($pgp->claveDeEmpresa($empresaB)->clave_privada);

    expect(fn () => $soloB->decrypt($cifradoParaA['descripcion']))->toThrow(PgpException::class);
});

test('la clave privada en la base no se abre con APP_KEY: usa su propio secreto', function () {
    $pgp = app(PgpService::class);
    $clave = $pgp->claveDeEmpresa(Empresa::factory()->aprobada()->create());

    $crudo = DB::table('claves_pgp_empresa')->where('id', $clave->id)->value('clave_privada');

    expect($crudo)->not->toContain('PRIVATE KEY')
        ->and(fn () => Crypt::decryptString($crudo))->toThrow(DecryptException::class)
        ->and($clave->fresh()->clave_privada)->toContain('PRIVATE KEY');
});

test('ver un reporte deja constancia de quien descifro su contenido', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaDeEmpresa(miembroDeEmpresa($empresa), ['estado' => 'activo', 'es_publico' => true]);
    $autor = investigador();
    $this->actingAs($autor);

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'SQLi',
        'descripcion' => 'El parametro id no se escapa',
        'poc' => ['evidencia' => "' OR 1=1 --"],
    ])->assertRedirect();

    $reporte = Reporte::where('titulo', 'SQLi')->firstOrFail();
    $this->get(route('reportes.show', $reporte))->assertOk();

    expect(Auditoria::where('accion', 'pgp.contenido_descifrado')
        ->where('entidad_type', 'Reporte')
        ->where('entidad_id', $reporte->id)
        ->where('usuario_id', $autor->id)
        ->exists())->toBeTrue();
});

test('pgp:restore reimporta tambien las claves de empresa en un llavero vacio', function () {
    $pgp = app(PgpService::class);
    $pgp->claveDeEmpresa(Empresa::factory()->aprobada()->create());

    $this->artisan('pgp:restore')
        ->expectsOutputToContain('Claves de empresa reimportadas: 1')
        ->assertSuccessful();
});

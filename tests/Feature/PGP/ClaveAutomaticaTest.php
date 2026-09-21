<?php

use App\Models\Auditoria;
use App\Models\ClavePgpPlataforma;
use App\Models\Programa;
use App\Models\Reporte;
use App\Services\Pgp\Contracts\PgpDriver;
use App\Services\Pgp\Exceptions\PgpDriverUnavailableException;
use App\Services\Pgp\Exceptions\PgpException;
use App\Services\Pgp\PgpService;
use Database\Seeders\ClavePgpSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->almacenPgp = sys_get_temp_dir().'/pgp_auto_'.uniqid();
    config(['pgp.fallback.store' => $this->almacenPgp]);
    config(['pgp.identity' => 'Plataforma de Prueba <pruebas@localhost>']);

    $this->app->forgetInstance(PgpDriver::class);
    $this->app->forgetInstance(PgpService::class);
});

afterEach(function () {
    if (is_dir($this->almacenPgp)) {
        (new Filesystem)->deleteDirectory($this->almacenPgp);
    }
});

/** Un driver que nunca puede crear claves (por ejemplo, GnuPG que no arranca bajo el servidor web). */
function driverQueFalla(): PgpDriver
{
    $driver = Mockery::mock(PgpDriver::class);
    $driver->shouldReceive('name')->andReturn('gpg');
    $driver->shouldReceive('available')->andReturn(false);
    $driver->shouldReceive('generateKeyPair')->andThrow(new PgpDriverUnavailableException('GnuPG no disponible'));

    return $driver;
}

function usarDriver(PgpDriver $driver): void
{
    app()->instance(PgpDriver::class, $driver);
    app()->forgetInstance(PgpService::class);
}

// ---------------------------------------------------------------------------
// La clave se crea sola
// ---------------------------------------------------------------------------

test('sin clave, cifrar un informe crea la clave sola y lo cifra con ella', function () {
    expect(ClavePgpPlataforma::count())->toBe(0);

    $cifrado = app(PgpService::class)->cifrarReporte('Descripción secreta', ['pasos' => 'uno']);

    $clave = ClavePgpPlataforma::firstOrFail();
    expect($clave->activa)->toBeTrue()
        ->and($cifrado['clave_huella'])->toBe($clave->huella)
        ->and($cifrado['descripcion'])->toContain('PGP MESSAGE')->not->toContain('Descripción secreta');
});

test('con clave existente no se crea otra', function () {
    $existente = app(PgpService::class)->generatePlatformKeyPair();

    app(PgpService::class)->cifrarReporte('uno');
    app(PgpService::class)->cifrarReporte('dos');
    app(PgpService::class)->asegurarClave();

    expect(ClavePgpPlataforma::count())->toBe(1)
        ->and(app(PgpService::class)->platformKey()->is($existente))->toBeTrue();
});

test('pedir la clave varias veces seguidas crea una sola', function () {
    $servicio = app(PgpService::class);

    $primera = $servicio->asegurarClave();
    $segunda = $servicio->asegurarClave();

    expect($primera->is($segunda))->toBeTrue()->and(ClavePgpPlataforma::count())->toBe(1);
});

test('si otra peticion tiene el candado y no hay clave, no se espera para siempre', function () {
    config(['pgp.creacion.espera_segundos' => 1]);
    $candado = Cache::lock('pgp:crear-clave', 30);
    expect($candado->get())->toBeTrue();

    try {
        expect(fn () => app(PgpService::class)->asegurarClave())->toThrow(PgpException::class, 'Otra petición');
        expect(ClavePgpPlataforma::count())->toBe(0);
    } finally {
        $candado->release();
    }
});

test('el candado se libera al terminar: una creacion posterior no queda bloqueada', function () {
    app(PgpService::class)->asegurarClave();
    ClavePgpPlataforma::query()->update(['activa' => false]);

    expect(app(PgpService::class)->asegurarClave())->toBeInstanceOf(ClavePgpPlataforma::class)
        ->and(ClavePgpPlataforma::where('activa', true)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Identidad unica
// ---------------------------------------------------------------------------

test('cada clave nueva lleva una identidad unica que conserva el nombre y el correo', function () {
    $servicio = app(PgpService::class);

    $a = $servicio->identidadUnica();
    $b = $servicio->identidadUnica();

    expect($a)->not->toBe($b)
        ->and($a)->toMatch('/^Plataforma de Prueba \(auto \d{8}-[a-z0-9]{4}\) <pruebas@localhost>$/')
        ->and($servicio->identidadUnica('Solo Nombre'))->toMatch('/^Solo Nombre \(auto \d{8}-[a-z0-9]{4}\)$/');
});

test('pgp:setup usa una identidad unica y regenerar no choca con la anterior', function () {
    Artisan::call('pgp:setup');
    Artisan::call('pgp:setup --force');

    $identidades = ClavePgpPlataforma::pluck('identidad');
    expect($identidades)->toHaveCount(2)->and($identidades->unique())->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// Constancia y aviso
// ---------------------------------------------------------------------------

test('crear la clave queda en auditoria como accion del sistema y avisa a los administradores, sin exponer la clave privada', function () {
    $admin = administrador();
    $otro = investigador();

    $clave = app(PgpService::class)->asegurarClave('automatico');

    $registro = Auditoria::where('accion', 'pgp.clave_generada')->firstOrFail();
    expect($registro->usuario_id)->toBeNull()
        ->and($registro->entidad_id)->toBe($clave->id)
        ->and($registro->detalle['huella'])->toBe($clave->huella)
        ->and($registro->detalle['origen'])->toBe('automatico')
        ->and(json_encode($registro->detalle))->not->toContain('PRIVATE');

    $aviso = $admin->notifications()->firstOrFail()->data;
    expect($aviso['titulo'])->toBe('Se creó la clave de cifrado')
        ->and($aviso['tipo'])->toBe('sistema')
        ->and($aviso['url'])->toBe('/admin/pgp')
        ->and($aviso['mensaje'])->toContain('al recibir un informe sin clave')
        ->and(json_encode($aviso))->not->toContain('PRIVATE')
        ->and($otro->notifications()->count())->toBe(0);
});

test('crearla al instalar lo dice en el aviso', function () {
    $admin = administrador();

    $this->seed(ClavePgpSeeder::class);

    expect($admin->notifications()->firstOrFail()->data['mensaje'])->toContain('durante la instalación')
        ->and(Auditoria::where('accion', 'pgp.clave_generada')->firstOrFail()->detalle['origen'])->toBe('instalacion');
});

// ---------------------------------------------------------------------------
// Instalacion
// ---------------------------------------------------------------------------

test('el seeder deja la clave lista y es idempotente', function () {
    $this->seed(ClavePgpSeeder::class);
    $this->seed(ClavePgpSeeder::class);

    expect(ClavePgpPlataforma::count())->toBe(1);
});

test('el seeder no falla si no se puede crear la clave ahora', function () {
    usarDriver(driverQueFalla());

    $this->seed(ClavePgpSeeder::class);

    expect(ClavePgpPlataforma::count())->toBe(0);
});

test('pgp:setup --tolerante no falla si no puede crear la clave, y sin la opcion si falla', function () {
    usarDriver(driverQueFalla());

    expect(Artisan::call('pgp:setup --tolerante'))->toBe(0)
        ->and(Artisan::call('pgp:setup'))->toBe(1);
});

// ---------------------------------------------------------------------------
// Produccion: contraseña obligatoria
// ---------------------------------------------------------------------------

test('en produccion se niega a crear la clave si no tiene contraseña', function () {
    $driver = Mockery::mock(PgpDriver::class);
    $driver->shouldReceive('name')->andReturn('gpg');
    $driver->shouldNotReceive('generateKeyPair');
    usarDriver($driver);
    config(['pgp.gpg.passphrase' => '']);
    app()->detectEnvironment(fn () => 'production');

    try {
        expect(fn () => app(PgpService::class)->asegurarClave())->toThrow(PgpException::class, 'PGP_KEY_PASSWORD');
        expect(ClavePgpPlataforma::count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});

// ---------------------------------------------------------------------------
// Si no se puede crear, el informe no se guarda y nunca en claro
// ---------------------------------------------------------------------------

test('si la clave no se puede crear el informe no se guarda y el investigador ve un aviso claro', function () {
    usarDriver(driverQueFalla());
    $investigador = investigador();
    $programa = Programa::factory()->create();

    $this->actingAs($investigador)->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'XSS en el buscador',
        'descripcion' => 'Texto que jamás debe quedar sin cifrar',
    ])->assertSessionHasErrors('pgp');

    expect(Reporte::count())->toBe(0)
        ->and(session('errors')->first('pgp'))->toContain('No se guardó nada sin cifrar');
});

test('con la clave creada sola el informe se guarda cifrado', function () {
    $investigador = investigador();
    $programa = Programa::factory()->create();

    $this->actingAs($investigador)->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'XSS en el buscador',
        'descripcion' => 'Descripción confidencial del hallazgo',
    ])->assertSessionHasNoErrors();

    $reporte = Reporte::firstOrFail();
    expect(ClavePgpPlataforma::count())->toBe(1)
        ->and($reporte->getRawOriginal('descripcion'))->toContain('PGP MESSAGE')->not->toContain('Descripción confidencial')
        ->and($reporte->clave_huella)->toBe(ClavePgpPlataforma::firstOrFail()->huella);
});

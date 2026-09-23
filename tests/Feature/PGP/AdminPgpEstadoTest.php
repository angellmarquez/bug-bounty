<?php

use App\Models\ClavePgpPlataforma;
use App\Services\Pgp\Contracts\PgpDriver;
use App\Services\Pgp\PgpService;
use Illuminate\Support\Facades\Route;

test('el administrador ve el estado del cifrado con la clave activa, sin la clave privada', function () {
    $clave = app(PgpService::class)->generatePlatformKeyPair();
    $this->actingAs(administrador());

    $respuesta = $this->get(route('admin.pgp'))->assertOk();

    $respuesta->assertInertia(fn ($page) => $page
        ->component('admin/pgp/Index')
        ->where('clave.id', $clave->id)
        ->where('driver', app(PgpService::class)->driverName())
        ->where('available', true)
        ->missing('clave.clave_privada')
        ->missing('clave.clave_publica')
        // La página ya no necesita mostrar huella/identidad/algoritmo/bits: solo si la
        // clave está activa y funcionando (evita exponer de más, aunque sea metadata).
        ->missing('clave.huella')
        ->missing('clave.identidad')
        ->missing('clave.algoritmo')
        ->missing('clave.bits'));

    $texto = json_encode($respuesta->inertiaProps());
    expect($texto)->not->toContain('PRIVATE KEY')->not->toContain($clave->getRawOriginal('clave_privada'));
});

test('sin clave la pagina lo dice y no ofrece crearla: la plataforma la crea sola', function () {
    expect(ClavePgpPlataforma::count())->toBe(0);
    $this->actingAs(administrador());

    $this->get(route('admin.pgp'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('clave', null)
        ->where('available', true));
});

test('la ruta para generar la clave a mano ya no existe', function () {
    expect(Route::has('admin.pgp.setup'))->toBeFalse();

    $this->actingAs(administrador())->post('/admin/pgp/setup')->assertNotFound();

    expect(ClavePgpPlataforma::count())->toBe(0);
});

test('si el driver no esta disponible la pagina lo indica', function () {
    $driver = Mockery::mock(PgpDriver::class);
    $driver->shouldReceive('name')->andReturn('gpg');
    $driver->shouldReceive('available')->andReturn(false);
    app()->instance(PgpDriver::class, $driver);
    app()->forgetInstance(PgpService::class);

    $this->actingAs(administrador())->get(route('admin.pgp'))->assertInertia(fn ($page) => $page
        ->where('driver', 'gpg')
        ->where('available', false)
        ->where('clave', null));
});

test('solo el administrador ve el estado del cifrado', function () {
    $this->actingAs(investigador())->get(route('admin.pgp'))->assertForbidden();
    $this->actingAs(moderador())->get(route('admin.pgp'))->assertForbidden();
    $this->actingAs(propietarioDeEmpresa())->get(route('admin.pgp'))->assertForbidden();
});

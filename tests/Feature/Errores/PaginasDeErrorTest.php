<?php

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

/**
 * Los errores se muestran con una página de la aplicación (no la pantalla blanca de Laravel),
 * sin filtrar reglas internas ni detalles de las excepciones.
 */
beforeEach(function () {
    Route::middleware('web')->group(function () {
        Route::get('/_prueba/explota', fn () => throw new RuntimeException('secreto-interno-123'));
        Route::get('/_prueba/mantenimiento', fn () => abort(503));
        Route::get('/_prueba/conflicto', fn () => abort(409, 'El informe ya fue cerrado por otro moderador.'));
        Route::get('/_prueba/prohibido-generico', fn () => abort(403));
        Route::post('/_prueba/expirada', fn () => throw new TokenMismatchException);
    });
});

test('una URL que no existe muestra la pagina 404 a un visitante sin sesion', function () {
    $this->get('/esto-no-existe')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', 404)
            ->where('mensaje', null)
            ->where('auth.user', null));
});

test('la pagina 404 sabe quien eres y reutiliza el menu de la aplicacion', function () {
    $usuario = investigador();

    $this->actingAs($usuario)->get('/esto-no-existe')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', 404)
            ->where('auth.user.id', $usuario->id));
});

test('un informe que no existe da 404 sin filtrar el mensaje interno del modelo', function () {
    $respuesta = $this->actingAs(investigador())->get('/reportes/999999')->assertNotFound();

    $respuesta->assertInertia(fn ($page) => $page->component('Error')->where('status', 404)->where('mensaje', null));

    expect($respuesta->getContent())->not->toContain('No query results')->not->toContain('App\Models');
});

test('un rechazo del sistema de permisos da 403 sin mostrar el nombre de las reglas', function () {
    $respuesta = $this->actingAs(investigador())->get('/admin/pgp')->assertForbidden();

    $respuesta->assertInertia(fn ($page) => $page->component('Error')->where('status', 403)->where('mensaje', null));

    expect($respuesta->getContent())->not->toContain('abac')->not->toContain('regla');
});

test('un 403 escrito con abort() muestra su mensaje en espanol al usuario', function () {
    Route::middleware(['web', 'auth', 'empresa.access'])->get('/_prueba/empresa', fn () => 'ok');

    // Una empresa sin aprobar todavia no opera: el propio codigo explica por que.
    $empresa = Empresa::factory()->create();
    $propietario = conRol(User::factory()->create(), 'empresa');
    $empresa->usuarios()->attach($propietario, ['rol_interno' => 'propietario', 'estado' => 'activo']);

    $this->actingAs($propietario)->get('/_prueba/empresa')
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', 403)
            ->where('mensaje', 'Tu empresa todavía no tiene acceso operativo.'));
});

test('un investigador que abre la gestion de programas ve el 403 de la aplicacion', function () {
    $this->actingAs(investigador())->get('/gestion/programas')
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 403)->where('mensaje', 'Solo las empresas gestionan programas.'));
});

test('un 403 generico de abort() no enseña el texto en ingles', function () {
    $this->get('/_prueba/prohibido-generico')
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 403)->where('mensaje', null));
});

test('un 409 con mensaje propio lo muestra', function () {
    $this->get('/_prueba/conflicto')
        ->assertStatus(409)
        ->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', 409)
            ->where('mensaje', 'El informe ya fue cerrado por otro moderador.'));
});

test('un abort(422) del sistema muestra su mensaje', function () {
    $admin = administrador();

    $this->actingAs($admin)->post(route('admin.moderadores.asignar', $admin))
        ->assertStatus(422)
        ->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', 422)
            ->where('mensaje', 'Un administrador no puede asignarse como moderador.'));
});

test('una ruta retirada da 404 con cualquier metodo, no un 405 confuso', function () {
    $this->actingAs(investigador())->post('/admin/pgp/setup')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));

    $this->actingAs(investigador())->delete('/dashboard')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
});

test('demasiadas peticiones seguidas dan 429 con el tiempo de espera', function () {
    config(['reportes.limites_envio.peticiones_por_minuto' => 1]);
    $investigador = investigador();
    $reporte = reporteDe($investigador);

    $this->actingAs($investigador)->post("/reportes/{$reporte->id}/enviar");

    $this->actingAs($investigador)->post("/reportes/{$reporte->id}/enviar")
        ->assertStatus(429)
        ->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', 429)
            ->where('reintentar_en', fn ($segundos) => $segundos >= 1 && $segundos <= 60));
});

test('un error interno con la depuracion apagada no enseña la excepcion', function () {
    config(['app.debug' => false]);

    $respuesta = $this->get('/_prueba/explota')
        ->assertStatus(500)
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 500)->where('mensaje', null));

    expect($respuesta->getContent())->not->toContain('secreto-interno-123')->not->toContain('RuntimeException');
});

test('con la depuracion encendida el desarrollador sigue viendo el detalle del error 500', function () {
    config(['app.debug' => true]);

    $this->get('/_prueba/explota')->assertStatus(500)->assertSee('secreto-interno-123', false);
});

test('el modo mantenimiento (503) tambien usa la pagina de la aplicacion', function () {
    $this->get('/_prueba/mantenimiento')
        ->assertStatus(503)
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 503));
});

test('una sesion expirada (419) vuelve atras con un aviso en vez de una pantalla de error', function () {
    $this->actingAs(investigador())
        ->from('/dashboard')
        ->post('/_prueba/expirada')
        ->assertRedirect('/dashboard')
        ->assertSessionHas('error');
});

test('las peticiones JSON siguen recibiendo JSON', function () {
    $this->getJson('/esto-no-existe')->assertNotFound()->assertJsonMissingPath('component');

    $this->actingAs(investigador())->getJson('/admin/pgp')->assertForbidden()->assertJsonStructure(['message']);
});

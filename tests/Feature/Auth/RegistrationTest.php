<?php

use App\Models\Rol;
use App\Models\User;
use App\Support\RequisitosContrasena;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('el formulario muestra los mismos requisitos de contrasena que valida el servidor', function () {
    $this->get(route('register'))
        ->assertInertia(fn ($page) => $page
            ->where('contrasena.minimo', 8)
            ->where('contrasena.texto', fn (string $texto) => str_starts_with($texto, 'Mínimo 8 caracteres')));

    $this->get(route('empresa.register'))
        ->assertInertia(fn ($page) => $page->where('contrasena.minimo', 8));
});

test('en produccion el formulario anuncia 12 caracteres y la comprobacion de filtraciones', function () {
    $this->app['env'] = 'production';

    $requisitos = RequisitosContrasena::paraFormulario();

    expect($requisitos['minimo'])->toBe(12)
        ->and($requisitos['texto'])->toStartWith('Mínimo 12 caracteres, con mayúsculas y minúsculas, números y símbolos.')
        ->and($requisitos['texto'])->toContain('filtraciones');
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Clave-Segura-2026',
        'password_confirmation' => 'Clave-Segura-2026',
        'terminos' => '1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('new users are assigned investigador role on registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Investigador Test',
        'email' => 'investigador@test.com',
        'password' => 'Clave-Segura-2026',
        'password_confirmation' => 'Clave-Segura-2026',
        'terminos' => '1',
    ]);

    $user = User::where('email', 'investigador@test.com')->first();
    $this->assertNotNull($user);
    $this->assertTrue($user->roles->contains('slug', 'investigador'));
});

test('investigador role is created if it does not exist', function () {
    Rol::where('slug', 'investigador')->delete();

    $this->post(route('register.store'), [
        'name' => 'Nuevo Investigador',
        'email' => 'nuevo@test.com',
        'password' => 'Clave-Segura-2026',
        'password_confirmation' => 'Clave-Segura-2026',
        'terminos' => '1',
    ]);

    $rol = Rol::where('slug', 'investigador')->first();
    $this->assertNotNull($rol);
    $this->assertEquals('Investigador', $rol->nombre);
});

test('registered user has exactly one role after registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Solo Un Rol',
        'email' => 'unrol@test.com',
        'password' => 'Clave-Segura-2026',
        'password_confirmation' => 'Clave-Segura-2026',
        'terminos' => '1',
    ]);

    $user = User::where('email', 'unrol@test.com')->first();
    $this->assertEquals(1, $user->roles->count());
});

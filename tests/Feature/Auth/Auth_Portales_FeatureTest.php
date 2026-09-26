<?php

use App\Models\Auditoria;

test('una empresa entra por su acceso', function () {
    $empresa = propietarioDeEmpresa();

    $this->post(route('login.store'), ['email' => $empresa->email, 'password' => 'password', 'portal' => 'empresa'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($empresa);
});

test('una empresa no entra por el acceso de investigadores', function () {
    $empresa = propietarioDeEmpresa();

    $this->post(route('login.store'), ['email' => $empresa->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Esta es una cuenta de empresa: inicia sesión desde el acceso para empresas.']);

    $this->assertGuest();
    expect(Auditoria::where('accion', 'auth.portal_incorrecto')->where('entidad_id', $empresa->id)->exists())->toBeTrue();
});

test('investigadores, moderadores y administradores no entran por el acceso de empresas', function (string $rol) {
    $cuenta = match ($rol) {
        'investigador' => investigador(),
        'moderador' => moderador(),
        default => administrador(),
    };

    $this->post(route('login.store'), ['email' => $cuenta->email, 'password' => 'password', 'portal' => 'empresa'])
        ->assertSessionHasErrors(['email' => 'Esta cuenta no es de empresa: inicia sesión desde el acceso para investigadores.']);

    $this->assertGuest();
})->with(['investigador', 'moderador', 'administrador']);

test('investigadores, moderadores y administradores entran por el acceso normal', function (string $rol) {
    $cuenta = match ($rol) {
        'investigador' => investigador(),
        'moderador' => moderador(),
        default => administrador(),
    };

    $this->post(route('login.store'), ['email' => $cuenta->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($cuenta);
})->with(['investigador', 'moderador', 'administrador']);

test('una contraseña incorrecta no revela de qué tipo es la cuenta', function () {
    $empresa = propietarioDeEmpresa();

    $this->post(route('login.store'), ['email' => $empresa->email, 'password' => 'incorrecta'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->not->toContain('empresa');
    $this->assertGuest();
});

test('el correo no distingue mayúsculas', function () {
    $cuenta = investigador(['email' => 'mixto@example.com']);

    $this->post(route('login.store'), ['email' => 'MIXTO@Example.com', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($cuenta);
});

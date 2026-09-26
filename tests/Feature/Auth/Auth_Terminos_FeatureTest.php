<?php

use App\Models\User;

function registroInvestigador(array $extra = []): array
{
    return [
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'password' => 'Clave-Segura-2026',
        'password_confirmation' => 'Clave-Segura-2026',
        ...$extra,
    ];
}

function registroEmpresa(array $extra = []): array
{
    return [
        'razon_social' => 'Acme Seguridad S.A.',
        'identificador_fiscal' => 'J-12345678-0',
        'empresa_email' => 'contacto@acme.test',
        'name' => 'María López',
        'email' => 'maria@acme.test',
        'password' => 'Password123!Password',
        'password_confirmation' => 'Password123!Password',
        ...$extra,
    ];
}

test('un investigador no puede registrarse sin aceptar los términos', function () {
    $this->post(route('register.store'), registroInvestigador())
        ->assertSessionHasErrors(['terminos' => 'Para crear la cuenta debes aceptar los Términos de Servicio y la Política de Privacidad.']);

    expect(User::where('email', 'ana@example.com')->exists())->toBeFalse();
});

test('al registrarse queda constancia de la versión aceptada', function () {
    $this->post(route('register.store'), registroInvestigador(['terminos' => '1']))->assertSessionHasNoErrors();

    $usuario = User::where('email', 'ana@example.com')->firstOrFail();
    expect($usuario->terminos_version)->toBe(config('legal.version'))
        ->and($usuario->terminos_aceptados_en)->not->toBeNull();
});

test('una empresa no puede registrarse sin aceptar los términos', function () {
    $this->post(route('empresa.register.store'), registroEmpresa())->assertSessionHasErrors('terminos');

    expect(User::where('email', 'maria@acme.test')->exists())->toBeFalse();
});

test('la empresa registrada guarda su aceptación', function () {
    $this->post(route('empresa.register.store'), registroEmpresa(['terminos' => '1']))->assertSessionHasNoErrors();

    expect(User::where('email', 'maria@acme.test')->firstOrFail()->terminos_version)->toBe(config('legal.version'));
});

test('las páginas legales son públicas', function (string $ruta, string $componente) {
    $this->get($ruta)->assertOk()->assertInertia(fn ($page) => $page->component($componente));
})->with([
    ['/terminos', 'legal/Terminos'],
    ['/privacidad', 'legal/Privacidad'],
    ['/politica-de-divulgacion', 'legal/Divulgacion'],
]);

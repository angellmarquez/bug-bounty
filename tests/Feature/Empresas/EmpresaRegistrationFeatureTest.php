<?php

use App\Enums\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\User;

test('company registration creates a pending company and owner membership', function () {
    $response = $this->post(route('empresa.register.store'), [
        'razon_social' => 'Acme Seguridad S.A.',
        'nombre_comercial' => 'Acme Security',
        'identificador_fiscal' => 'RFC-ACME-001',
        'empresa_email' => 'seguridad@acme.test',
        'name' => 'Ana Responsable',
        'email' => 'ana@acme.test',
        'password' => 'Password123!Password',
        'password_confirmation' => 'Password123!Password',
    ]);

    $response->assertRedirect(route('empresa.dashboard'));

    $empresa = Empresa::query()->where('identificador_fiscal', 'RFC-ACME-001')->firstOrFail();
    $usuario = User::query()->where('email', 'ana@acme.test')->firstOrFail();

    expect($empresa->estado)->toBe(EstadoEmpresa::Pendiente)
        ->and($empresa->usuarios()->whereKey($usuario->id)->first()->pivot->rol_interno)->toBe('propietario')
        ->and($usuario->roles()->where('slug', 'empresa')->exists())->toBeTrue();

    $this->assertAuthenticatedAs($usuario);
});

test('company dashboard requires company membership', function () {
    $user = investigador();
    $this->actingAs($user);

    $this->get(route('empresa.dashboard'))->assertForbidden();
});
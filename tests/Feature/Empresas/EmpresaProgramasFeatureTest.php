<?php

use App\Enums\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\Programa;
use App\Models\User;

function usuarioEmpresa(Empresa $empresa, string $email): User
{
    $usuario = conRol(User::factory()->create(['email' => $email]), 'empresa');
    $empresa->usuarios()->attach($usuario, [
        'rol_interno' => 'propietario',
        'estado' => 'activo',
    ]);

    return $usuario;
}

test('approved company owns programs created by its user', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $usuario = usuarioEmpresa($empresa, 'empresa-programas@example.com');
    $this->actingAs($usuario);

    $response = $this->post(route('programas.store'), [
        'nombre' => 'Programa Acme',
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.acme.test']],
        'descripcion' => 'Programa de seguridad de Acme.',
        'recompensa_min' => 100,
        'recompensa_max' => 1000,
        'moneda' => 'USD',
    ]);

    $response->assertRedirect();
    $programa = Programa::where('nombre', 'Programa Acme')->firstOrFail();

    expect($programa->empresa_id)->toBe($empresa->id);
});

test('pending company cannot create programs', function () {
    $empresa = Empresa::factory()->create(['estado' => EstadoEmpresa::Pendiente]);
    $usuario = usuarioEmpresa($empresa, 'empresa-pendiente@example.com');
    $this->actingAs($usuario);

    $this->post(route('programas.store'), [
        'nombre' => 'Programa bloqueado',
        'descripcion' => 'No debería crearse.',
        'recompensa_min' => 100,
        'recompensa_max' => 1000,
        'moneda' => 'USD',
    ])->assertForbidden();
});

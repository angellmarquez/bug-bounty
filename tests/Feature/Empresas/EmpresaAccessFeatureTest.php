<?php

use App\Enums\EstadoEmpresa;
use App\Models\Empresa;

test('pending company cannot access operational routes', function () {
    $empresa = Empresa::factory()->create();
    $usuario = usuarioEmpresa($empresa, 'empresa-access@example.com');
    $this->actingAs($usuario);

    $this->get(route('reportes.index'))->assertForbidden();
    $this->get(route('empresa.dashboard'))->assertOk();
});

test('administrator can reactivate suspended company', function () {
    $admin = administrador();
    $empresa = Empresa::factory()->create(['estado' => EstadoEmpresa::Suspendida]);
    $this->actingAs($admin);

    $this->post(route('admin.empresas.reactivar', $empresa))->assertRedirect(route('admin.empresas'));

    expect($empresa->fresh()->estado)->toBe(EstadoEmpresa::Aprobada);
});

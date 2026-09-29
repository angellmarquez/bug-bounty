<?php

use App\Enums\EstadoEmpresa;
use App\Models\Auditoria;
use App\Models\Empresa;

test('administrator can approve a pending company', function () {
    $admin = administrador();
    $empresa = Empresa::factory()->create();
    $this->actingAs($admin);

    $response = $this->post(route('admin.empresas.aprobar', $empresa), [
        'motivo' => 'Documentación verificada.',
    ]);

    $response->assertRedirect(route('admin.empresas'));
    expect($empresa->fresh()->estado)->toBe(EstadoEmpresa::Aprobada)
        ->and($empresa->fresh()->aprobado_por)->toBe($admin->id);
    expect(Auditoria::where('entidad_type', 'Empresa')->where('entidad_id', $empresa->id)->where('accion', 'admin.empresa.aprobada')->exists())->toBeTrue();
});

test('cada decision sobre una empresa solo vale desde su estado', function (string $estado, string $ruta, bool $permitido) {
    $this->actingAs(administrador());
    $empresa = Empresa::factory()->create(['estado' => $estado]);

    $respuesta = $this->post(route("admin.empresas.{$ruta}", $empresa), ['motivo' => 'Motivo de prueba']);

    $permitido ? $respuesta->assertSessionHasNoErrors() : $respuesta->assertSessionHasErrors('estado');
    if (! $permitido) {
        expect($empresa->fresh()->estado->value)->toBe($estado);
    }
})->with([
    'reactivar una pendiente (la aprobaria sin revisar)' => ['pendiente', 'reactivar', false],
    'suspender una pendiente' => ['pendiente', 'suspender', false],
    'aprobar una suspendida' => ['suspendida', 'aprobar', false],
    'rechazar una aprobada' => ['aprobada', 'rechazar', false],
    'reactivar una suspendida' => ['suspendida', 'reactivar', true],
    'suspender una aprobada' => ['aprobada', 'suspender', true],
]);

test('non administrator cannot approve a company', function () {
    $user = moderador();
    $empresa = Empresa::factory()->create();
    $this->actingAs($user);

    $this->post(route('admin.empresas.aprobar', $empresa))
        ->assertForbidden();

    expect($empresa->fresh()->estado)->toBe(EstadoEmpresa::Pendiente);
});

test('administrator can reject a company with a reason', function () {
    $admin = administrador();
    $empresa = Empresa::factory()->create();
    $this->actingAs($admin);

    $this->post(route('admin.empresas.rechazar', $empresa), [
        'motivo' => 'Identificador fiscal no verificable.',
    ])->assertRedirect(route('admin.empresas'));

    expect($empresa->fresh()->estado)->toBe(EstadoEmpresa::Rechazada)
        ->and($empresa->fresh()->motivo_estado)->toBe('Identificador fiscal no verificable.');
});

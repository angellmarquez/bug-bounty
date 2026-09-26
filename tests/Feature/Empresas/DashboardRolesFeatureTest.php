<?php

use App\Models\Empresa;
use App\Models\Programa;

test('company dashboard includes company metrics', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $usuario = usuarioEmpresa($empresa, 'dashboard-company@example.com');
    Programa::factory()->create(['empresa_id' => $empresa->id, 'creado_por' => $usuario->id, 'estado' => 'activo']);
    $this->actingAs($usuario);

    $props = $this->get(route('dashboard'))->inertiaProps();

    expect($props['roleStats']['empresa']['tipo'])->toBe('empresa')
        ->and($props['roleStats']['empresa']['programas_activos'])->toBe(1);
});

test('moderator dashboard includes moderation metrics', function () {
    $moderador = investigador();
    $moderador->roles()->syncWithoutDetaching([rol('moderador')->id]);
    $this->actingAs($moderador);

    $props = $this->get(route('dashboard'))->inertiaProps();

    expect($props['roleStats']['moderador']['tipo'])->toBe('moderador')
        ->and($props['roleStats']['moderador'])->toHaveKeys(['pendientes_revision', 'validados', 'rechazados', 'sanciones_aplicadas']);
});


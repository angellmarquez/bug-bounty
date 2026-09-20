<?php

use App\Enums\EstadoEmpresa;
use App\Models\Empresa;

test('empresa stores approval state and user membership', function () {
    $owner = investigador();
    $empresa = Empresa::factory()->create();

    $empresa->usuarios()->attach($owner, [
        'rol_interno' => 'propietario',
        'estado' => 'activo',
        'aceptado_en' => now(),
    ]);

    expect($empresa->fresh()->estado)->toBe(EstadoEmpresa::Pendiente)
        ->and($empresa->fresh()->usuarios)->toHaveCount(1)
        ->and($owner->fresh()->empresas)->toHaveCount(1)
        ->and($owner->fresh()->empresas->first()->pivot->rol_interno)->toBe('propietario');
});

test('empresa factory can create an approved company', function () {
    $empresa = Empresa::factory()->aprobada()->create();

    expect($empresa->estado)->toBe(EstadoEmpresa::Aprobada)
        ->and($empresa->aprobado_en)->not->toBeNull();
});

<?php

use App\Enums\Severidad;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Reputacion\ReputationService;

test('calcularPuntosEvento devuelve los puntos configurados según severidad técnica', function () {
    $servicio = app(ReputationService::class);

    $reporteCritico = Reporte::factory()->create(['severidad' => Severidad::Critica]);
    $reporteAlto = Reporte::factory()->create(['severidad' => Severidad::Alta]);
    $reporteMedio = Reporte::factory()->create(['severidad' => Severidad::Media]);
    $reporteBajo = Reporte::factory()->create(['severidad' => Severidad::Baja]);

    // Validación
    expect($servicio->calcularPuntosEvento('reporte_validado', $reporteCritico))->toBe(100)
        ->and($servicio->calcularPuntosEvento('reporte_validado', $reporteAlto))->toBe(50)
        ->and($servicio->calcularPuntosEvento('reporte_validado', $reporteMedio))->toBe(25)
        ->and($servicio->calcularPuntosEvento('reporte_validado', $reporteBajo))->toBe(10);

    // Resolución
    expect($servicio->calcularPuntosEvento('reporte_resuelto', $reporteCritico))->toBe(200)
        ->and($servicio->calcularPuntosEvento('reporte_resuelto', $reporteAlto))->toBe(100)
        ->and($servicio->calcularPuntosEvento('reporte_resuelto', $reporteMedio))->toBe(50)
        ->and($servicio->calcularPuntosEvento('reporte_resuelto', $reporteBajo))->toBe(20);
});

test('otorgarPuntosEvento registra entrada en el ledger con la puntuación justa por severidad', function () {
    $investigador = User::factory()->create(['reputation_score' => 0]);
    $servicio = app(ReputationService::class);

    $reporte = Reporte::factory()->create([
        'investigador_id' => $investigador->id,
        'severidad' => Severidad::Critica,
    ]);

    $entrada = $servicio->otorgarPuntosEvento($investigador, 'reporte_validado', $reporte);

    expect($entrada)->not->toBeNull()
        ->and($entrada->puntos)->toBe(100)
        ->and($investigador->fresh()->reputation_score)->toBe(100);

    $entradaResolucion = $servicio->otorgarPuntosEvento($investigador, 'reporte_resuelto', $reporte);

    expect($entradaResolucion)->not->toBeNull()
        ->and($entradaResolucion->puntos)->toBe(200)
        ->and($investigador->fresh()->reputation_score)->toBe(300);
});

<?php

use App\Models\Reporte;
use App\Models\User;
use App\Services\Reputacion\ReputationService;

test('reputacion:audit lista el resumen global', function () {
    User::factory()->count(2)->create();

    $this->artisan('reputacion:audit')
        ->expectsOutputToContain('=== Reputación')
        ->assertExitCode(0);
});

test('reputacion:audit muestra el detalle del ledger de un usuario', function () {
    $investigador = investigador();
    app(ReputationService::class)->asentar($investigador->id, 50, 'reporte_validado');

    $this->artisan('reputacion:audit', ['--usuario' => $investigador->id])
        ->expectsOutputToContain('reporte_validado')
        ->assertExitCode(0);
});

test('reputacion:audit --analizar reporta detecciones sin aplicar sanciones', function () {
    $investigador = investigador();

    Reporte::factory()->create([
        'investigador_id' => $investigador->id,
        'estado' => 'rechazado',
        'severidad' => 'critica',
        'poc' => null,
    ]);

    $this->artisan('reputacion:audit --analizar')
        ->expectsOutputToContain('fabricacion_evidencia')
        ->assertExitCode(0);

    expect($investigador->fresh()->reputation_score)->toBe(0);
});

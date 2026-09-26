<?php

use App\Models\Empresa;
use App\Models\Programa;
use App\Services\Reputacion\ReputationService;

test('reporte de severidad critica otorga puntos ponderados automaticamente', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $investigador = investigador();
    $servicio = app(ReputationService::class);

    $reporteCritico = reporteDe($investigador, $programa, [
        'severidad' => 'critica',
        'puntuacion_cvss' => 9.8,
    ]);

    $entradaValidado = $servicio->otorgarPuntosEvento($investigador, 'reporte_validado', $reporteCritico);
    expect($entradaValidado->puntos)->toBe(100)
        ->and($investigador->fresh()->reputation_score)->toBe(100);

    $entradaResuelto = $servicio->otorgarPuntosEvento($investigador, 'reporte_resuelto', $reporteCritico);
    expect($entradaResuelto->puntos)->toBe(200)
        ->and($investigador->fresh()->reputation_score)->toBe(300);
});

test('reporte de severidad media otorga puntos correspondientes', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $investigador = investigador();
    $servicio = app(ReputationService::class);

    $reporteMedio = reporteDe($investigador, $programa, [
        'severidad' => 'media',
        'puntuacion_cvss' => 5.5,
    ]);

    $entradaValidado = $servicio->otorgarPuntosEvento($investigador, 'reporte_validado', $reporteMedio);
    expect($entradaValidado->puntos)->toBe(25);

    $entradaResuelto = $servicio->otorgarPuntosEvento($investigador, 'reporte_resuelto', $reporteMedio);
    expect($entradaResuelto->puntos)->toBe(50)
        ->and($investigador->fresh()->reputation_score)->toBe(75);
});

test('reporte de severidad baja otorga puntos correspondientes', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $investigador = investigador();
    $servicio = app(ReputationService::class);

    $reporteBajo = reporteDe($investigador, $programa, [
        'severidad' => 'baja',
        'puntuacion_cvss' => 2.5,
    ]);

    $entradaValidado = $servicio->otorgarPuntosEvento($investigador, 'reporte_validado', $reporteBajo);
    expect($entradaValidado->puntos)->toBe(10);

    $entradaResuelto = $servicio->otorgarPuntosEvento($investigador, 'reporte_resuelto', $reporteBajo);
    expect($entradaResuelto->puntos)->toBe(20)
        ->and($investigador->fresh()->reputation_score)->toBe(30);
});

test('evento sin reporte o sin severidad usa los puntos base configurados', function () {
    $investigador = investigador();
    $servicio = app(ReputationService::class);

    $entrada = $servicio->otorgarPuntosEvento($investigador, 'reporte_validado');
    expect($entrada->puntos)->toBe((int) config('reputacion.puntos.reporte_validado'));
});

<?php

use App\Enums\GravedadSancion;
use App\Models\Reporte;
use App\Models\Sancion;
use App\Services\Reputacion\CheatDetectionService;

test('detecta una ráfaga de reportes enviados en la ventana configurada', function () {
    $investigador = investigador();

    for ($i = 0; $i < 6; $i++) {
        Reporte::factory()->create([
            'investigador_id' => $investigador->id,
            'enviado_en' => now()->subMinutes(5),
        ]);
    }

    $deteccion = app(CheatDetectionService::class)->analizarRafaga($investigador);

    expect($deteccion)->not->toBeNull()
        ->and($deteccion->motivo)->toBe('rafaga_reportes')
        ->and($deteccion->gravedad)->toBe(GravedadSancion::Media)
        ->and($deteccion->metadata['reportes'])->toBe(6);
});

test('los reportes antiguos no disparan la detección de ráfaga', function () {
    $investigador = investigador();

    for ($i = 0; $i < 6; $i++) {
        Reporte::factory()->create([
            'investigador_id' => $investigador->id,
            'enviado_en' => now()->subHours(2),
        ]);
    }

    expect(app(CheatDetectionService::class)->analizarRafaga($investigador))->toBeNull();
});

test('detecta una tasa alta de reportes duplicados', function () {
    $investigador = investigador();

    for ($i = 0; $i < 6; $i++) {
        Reporte::factory()->create([
            'investigador_id' => $investigador->id,
            'estado' => 'duplicado',
            'es_duplicado_de' => Reporte::factory(),
        ]);
    }

    $deteccion = app(CheatDetectionService::class)->analizarDuplicados($investigador);

    expect($deteccion)->not->toBeNull()
        ->and($deteccion->motivo)->toBe('reporte_duplicado')
        ->and($deteccion->gravedad)->toBe(GravedadSancion::Media)
        ->and($deteccion->metadata['duplicados'])->toBe(6);
});

test('un número anecdótico de duplicados no activa la detección', function () {
    $investigador = investigador();

    for ($i = 0; $i < 5; $i++) {
        Reporte::factory()->create(['investigador_id' => $investigador->id]);
    }

    Reporte::factory()->create([
        'investigador_id' => $investigador->id,
        'estado' => 'duplicado',
        'es_duplicado_de' => Reporte::factory(),
    ]);

    expect(app(CheatDetectionService::class)->analizarDuplicados($investigador))->toBeNull();
});

test('detecta fabricación de evidencia en reportes graves sin PoC', function () {
    $investigador = investigador();

    $reporte = Reporte::factory()->create([
        'investigador_id' => $investigador->id,
        'estado' => 'rechazado',
        'severidad' => 'critica',
        'poc' => null,
    ]);

    $detecciones = app(CheatDetectionService::class)->analizarFabricaciones($investigador);

    expect($detecciones)->toHaveCount(1)
        ->and($detecciones[0]->motivo)->toBe('fabricacion_evidencia')
        ->and($detecciones[0]->gravedad)->toBe(GravedadSancion::Grave)
        ->and($detecciones[0]->reporte_id)->toBe($reporte->id);
});

test('los reportes con PoC válida no se consideran fabricación', function () {
    $investigador = investigador();

    Reporte::factory()->create([
        'investigador_id' => $investigador->id,
        'estado' => 'rechazado',
        'severidad' => 'critica',
        'poc' => ['pasos' => 'curl -k https://...', 'evidencia' => '<script>alert(1)</script>'],
    ]);

    expect(app(CheatDetectionService::class)->analizarFabricaciones($investigador))->toBeEmpty();
});

test('analizar detecta la fabricación de un reporte concreto', function () {
    $reporte = Reporte::factory()->create([
        'estado' => 'rechazado',
        'severidad' => 'alta',
        'poc' => null,
    ]);

    $legitimo = Reporte::factory()->create([
        'estado' => 'rechazado',
        'severidad' => 'alta',
        'poc' => ['pasos' => 'curl', 'evidencia' => 'output'],
    ]);

    expect(app(CheatDetectionService::class)->analizar($reporte))->toHaveCount(1)
        ->and(app(CheatDetectionService::class)->analizar($legitimo))->toBeEmpty();
});

test('analizarUsuario agrega todas las detecciones del usuario', function () {
    $investigador = investigador();

    for ($i = 0; $i < 6; $i++) {
        Reporte::factory()->create([
            'investigador_id' => $investigador->id,
            'enviado_en' => now()->subMinutes(3),
            'estado' => 'duplicado',
            'es_duplicado_de' => Reporte::factory(),
            'severidad' => 'critica',
            'poc' => null,
        ]);
    }

    $detecciones = app(CheatDetectionService::class)->analizarUsuario($investigador);

    $motivos = collect($detecciones)->pluck('motivo')->unique()->values()->all();

    expect($detecciones)->toBeArray()
        ->and($motivos)->toContain('rafaga_reportes')
        ->and($motivos)->toContain('reporte_duplicado')
        ->and($motivos)->toContain('fabricacion_evidencia');
});

test('ejecutar aplica sanciones proporcionales y es idempotente', function () {
    $investigador = investigador();

    Reporte::factory()->create([
        'investigador_id' => $investigador->id,
        'estado' => 'rechazado',
        'severidad' => 'critica',
        'poc' => null,
    ]);

    $servicio = app(CheatDetectionService::class);

    $aplicadas = $servicio->ejecutar($investigador);

    expect($aplicadas)->toHaveCount(1)
        ->and($aplicadas[0])->toBeInstanceOf(Sancion::class)
        ->and($aplicadas[0]->motivo)->toBe('fabricacion_evidencia')
        ->and($investigador->fresh()->reputation_score)->toBe((int) config('reputacion.penalizacion.grave'));

    expect($servicio->ejecutar($investigador->fresh()))->toBeEmpty();
});

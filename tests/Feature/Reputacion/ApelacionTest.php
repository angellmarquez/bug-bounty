<?php

use App\Enums\EstadoApelacion;
use App\Enums\EstadoSancion;
use App\Enums\GravedadSancion;
use App\Models\Apelacion;
use App\Models\EntradaReputacion;
use App\Models\Sancion;
use App\Services\Reputacion\ReputationService;

test('crearApelacion registra la apelación y pasa la sanción a apelada', function () {
    $investigador = investigador();
    $servicio = app(ReputationService::class);

    $sancion = $servicio->aplicarSancion($investigador, 'rafaga_reportes', GravedadSancion::Media);
    $apelacion = $servicio->crearApelacion($sancion, $investigador, 'No hubo ráfaga', ['evidencia' => 'registros']);

    expect($apelacion->estado)->toBe(EstadoApelacion::Pendiente)
        ->and($apelacion->evidencia)->toBe(['evidencia' => 'registros'])
        ->and($sancion->fresh()->estado)->toBe(EstadoSancion::Apelada);
});

test('crearApelacion rechaza apelar una sanción de otro usuario', function () {
    $sancion = Sancion::factory()->create(['usuario_id' => investigador()->id]);

    expect(fn () => app(ReputationService::class)->crearApelacion($sancion, investigador(), 'mía'))
        ->toThrow(InvalidArgumentException::class);
});

test('crearApelacion rechaza apelar fuera de plazo', function () {
    $investigador = investigador();
    $sancion = Sancion::factory()->create([
        'usuario_id' => $investigador->id,
        'estado' => 'aplicada',
        'plazo_apelacion' => now()->subDay(),
    ]);

    expect(fn () => app(ReputationService::class)->crearApelacion($sancion, $investigador, 'fuera de plazo'))
        ->toThrow(InvalidArgumentException::class);
});

test('crearApelacion rechaza apelar una sanción revocada', function () {
    $investigador = investigador();
    $servicio = app(ReputationService::class);

    $sancion = $servicio->aplicarSancion($investigador, 'rafaga_reportes', GravedadSancion::Media);
    $servicio->revocarSancion($sancion);

    expect(fn () => $servicio->crearApelacion($sancion->fresh(), $investigador, 'revocada'))
        ->toThrow(InvalidArgumentException::class);
});

test('una sanción no admite dos apelaciones pendientes', function () {
    $investigador = investigador();
    $servicio = app(ReputationService::class);

    $sancion = $servicio->aplicarSancion($investigador, 'rafaga_reportes', GravedadSancion::Media);
    $servicio->crearApelacion($sancion, $investigador, 'primera');

    expect(fn () => $servicio->crearApelacion($sancion->fresh(), $investigador, 'segunda'))
        ->toThrow(InvalidArgumentException::class)
        ->and(Apelacion::count())->toBe(1);
});

test('resolverApelacion aprobada revoca la sanción y revierte el saldo', function () {
    $investigador = investigador();
    $reporte = reporteDe($investigador);
    $resolutor = gestion();
    $servicio = app(ReputationService::class);

    $sancion = $servicio->aplicarSancion($investigador, 'fabricacion_evidencia', GravedadSancion::Grave, $reporte);
    $apelacion = $servicio->crearApelacion($sancion, $investigador, 'No fue fabricación');

    $servicio->resolverApelacion($apelacion, aprobada: true, resolutor: $resolutor, nota: 'Evidencia suficiente');

    expect($apelacion->fresh()->estado)->toBe(EstadoApelacion::Aprobada)
        ->and($apelacion->fresh()->resuelta_por)->toBe($resolutor->id)
        ->and($apelacion->fresh()->resuelta_en)->not->toBeNull()
        ->and($sancion->fresh()->estado)->toBe(EstadoSancion::Revocada)
        ->and($investigador->fresh()->reputation_score)->toBe(0);
});

test('resolverApelacion rechazada mantiene la sanción aplicada', function () {
    $investigador = investigador();
    $resolutor = gestion();
    $servicio = app(ReputationService::class);

    $sancion = $servicio->aplicarSancion($investigador, 'fabricacion_evidencia', GravedadSancion::Grave);
    $apelacion = $servicio->crearApelacion($sancion, $investigador, 'Apelo');

    $servicio->resolverApelacion($apelacion, aprobada: false, resolutor: $resolutor, nota: 'Sin sustento');

    expect($apelacion->fresh()->estado)->toBe(EstadoApelacion::Rechazada)
        ->and($sancion->fresh()->estado)->toBe(EstadoSancion::Aplicada)
        ->and($investigador->fresh()->reputation_score)->toBe((int) config('reputacion.penalizacion.grave'))
        ->and(EntradaReputacion::where('sancion_id', $sancion->id)->count())->toBe(1);
});

test('resolverApelacion rechaza resolver una apelación ya resuelta', function () {
    $investigador = investigador();
    $resolutor = gestion();
    $servicio = app(ReputationService::class);

    $sancion = $servicio->aplicarSancion($investigador, 'rafaga_reportes', GravedadSancion::Media);
    $apelacion = $servicio->crearApelacion($sancion, $investigador, 'Apelo');

    $servicio->resolverApelacion($apelacion, aprobada: false, resolutor: $resolutor);

    expect(fn () => $servicio->resolverApelacion($apelacion->fresh(), aprobada: true, resolutor: $resolutor))
        ->toThrow(InvalidArgumentException::class);
});

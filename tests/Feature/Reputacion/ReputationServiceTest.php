<?php

use App\Enums\EstadoSancion;
use App\Enums\GravedadSancion;
use App\Enums\TipoEventoReporte;
use App\Models\Auditoria;
use App\Models\EntradaReputacion;
use App\Models\EventoReporte;
use App\Models\User;
use App\Services\Reputacion\ReputationService;
use RuntimeException;

test('asentar registra un asiento en el ledger y sincroniza el score', function () {
    $usuario = User::factory()->create(['reputation_score' => 0]);

    $entrada = app(ReputationService::class)->asentar($usuario->id, 50, 'reporte_validado');

    expect($entrada->puntos)->toBe(50)
        ->and($entrada->motivo)->toBe('reporte_validado')
        ->and(EntradaReputacion::count())->toBe(1)
        ->and($usuario->fresh()->reputation_score)->toBe(50);
});

test('el ledger no admite asientos de cero puntos', function () {
    expect(fn () => app(ReputationService::class)->asentar(1, 0, 'sin_puntos'))
        ->toThrow(InvalidArgumentException::class);
});

test('saldo refleja la suma del ledger independiente de la columna', function () {
    $usuario = User::factory()->create();
    $servicio = app(ReputationService::class);

    $servicio->asentar($usuario->id, 50, 'reporte_validado');
    $servicio->asentar($usuario->id, -25, 'sancion_leve');

    $usuario->update(['reputation_score' => 999]);

    expect($servicio->saldo($usuario))->toBe(25);
});

test('el ledger es inmutable: no permite actualizar ni eliminar asientos', function () {
    $usuario = User::factory()->create();
    $entrada = EntradaReputacion::factory()->create(['usuario_id' => $usuario->id]);

    expect(fn () => $entrada->update(['puntos' => 0]))->toThrow(RuntimeException::class)
        ->and(fn () => $entrada->delete())->toThrow(RuntimeException::class)
        ->and(EntradaReputacion::count())->toBe(1);
});

test('la auditoría es inmutable: no permite actualizar ni eliminar registros', function () {
    $auditoria = Auditoria::factory()->create();

    expect(fn () => $auditoria->update(['accion' => 'modificada']))->toThrow(RuntimeException::class)
        ->and(fn () => $auditoria->delete())->toThrow(RuntimeException::class);
});

test('sincronizar reconcilia la columna con el ledger', function () {
    $usuario = User::factory()->create();
    $servicio = app(ReputationService::class);

    $servicio->asentar($usuario->id, 40, 'reporte_validado');
    $servicio->asentar($usuario->id, -10, 'sancion_leve');

    $usuario->update(['reputation_score' => 0]);

    expect($servicio->sincronizar($usuario))->toBe(30)
        ->and($usuario->fresh()->reputation_score)->toBe(30);
});

test('otorgarPuntosEvento premia con los puntos configurados', function () {
    $usuario = User::factory()->create();

    $entrada = app(ReputationService::class)->otorgarPuntosEvento($usuario, 'reporte_resuelto');

    expect($entrada)->not->toBeNull()
        ->and($entrada->puntos)->toBe((int) config('reputacion.puntos.reporte_resuelto'))
        ->and($usuario->fresh()->reputation_score)->toBe((int) config('reputacion.puntos.reporte_resuelto'));
});

test('otorgarPuntosEvento no asienta cuando el evento no tiene puntos', function () {
    $usuario = User::factory()->create();

    expect(app(ReputationService::class)->otorgarPuntosEvento($usuario, 'evento_inexistente'))->toBeNull()
        ->and(EntradaReputacion::count())->toBe(0);
});

test('aplicarSancion crea la sanción, asienta la penalización y registra el timeline', function () {
    $reporte = reporteDe(investigador());
    $servicio = app(ReputationService::class);

    $sancion = $servicio->aplicarSancion($reporte->investigador_id, 'fabricacion_evidencia', GravedadSancion::Grave, $reporte);

    expect($sancion->estado)->toBe(EstadoSancion::Aplicada)
        ->and($sancion->plazo_apelacion->greaterThan(now()))->toBeTrue()
        ->and($sancion->puntos)->toBe((int) config('reputacion.penalizacion.grave'))
        ->and(EntradaReputacion::where('sancion_id', $sancion->id)->value('puntos'))->toBe((int) config('reputacion.penalizacion.grave'))
        ->and(EventoReporte::where('reporte_id', $reporte->id)->where('tipo', TipoEventoReporte::Sancion->value)->exists())->toBeTrue()
        ->and($reporte->investigador->fresh()->reputation_score)->toBe((int) config('reputacion.penalizacion.grave'));
});

test('aplicarSancion escala la penalización con la reincidencia', function () {
    $investigador = investigador();
    $servicio = app(ReputationService::class);
    $baseMedia = (int) config('reputacion.penalizacion.media');

    $servicio->aplicarSancion($investigador, 'rafaga_reportes', GravedadSancion::Media);

    $segunda = $servicio->aplicarSancion($investigador, 'rafaga_reportes', GravedadSancion::Media);

    $esperado = (int) round($baseMedia * 1.5);

    expect($segunda->puntos)->toBe($esperado);
});

test('aplicarSancion fija la ventana de suspensión según la gravedad', function () {
    $investigador = investigador();
    $servicio = app(ReputationService::class);

    $media = $servicio->aplicarSancion($investigador, 'reporte_duplicado', GravedadSancion::Media);
    $grave = $servicio->aplicarSancion($investigador, 'fabricacion_evidencia', GravedadSancion::Grave);

    expect(abs((int) $media->suspension_hasta->diffInDays($media->suspension_desde)))->toBe((int) config('reputacion.suspension.media.dias'))
        ->and(abs((int) $grave->suspension_hasta->diffInDays($grave->suspension_desde)))->toBe((int) config('reputacion.suspension.grave.dias'));

    $leve = $servicio->aplicarSancion($investigador, 'prueba', GravedadSancion::Leve);

    expect($leve->suspension_desde)->toBeNull()
        ->and($leve->suspension_hasta)->toBeNull();
});

test('revocarSancion devuelve el saldo y deja la sanción revocada', function () {
    $investigador = investigador();
    $reporte = reporteDe($investigador);
    $servicio = app(ReputationService::class);

    $sancion = $servicio->aplicarSancion($investigador, 'fabricacion_evidencia', GravedadSancion::Grave, $reporte);

    $servicio->revocarSancion($sancion, 'Apelación fundada');

    expect($sancion->fresh()->estado)->toBe(EstadoSancion::Revocada)
        ->and($investigador->fresh()->reputation_score)->toBe(0)
        ->and(EntradaReputacion::where('sancion_id', $sancion->id)->count())->toBe(2)
        ->and(EntradaReputacion::where('sancion_id', $sancion->id)->where('puntos', '>', 0)->exists())->toBeTrue()
        ->and(EntradaReputacion::where('sancion_id', $sancion->id)->where('motivo', 'reversion_sancion')->exists())->toBeTrue();
});

test('no se puede revocar una sanción ya revocada', function () {
    $investigador = investigador();
    $servicio = app(ReputationService::class);

    $sancion = $servicio->aplicarSancion($investigador, 'rafaga_reportes', GravedadSancion::Media);

    $servicio->revocarSancion($sancion);

    expect(fn () => $servicio->revocarSancion($sancion->fresh()))->toThrow(InvalidArgumentException::class);
});

test('recalcular reconciliar la reputación de todos los usuarios', function () {
    $primero = User::factory()->create();
    $segundo = User::factory()->create();
    $servicio = app(ReputationService::class);

    $servicio->asentar($primero->id, 50, 'reporte_validado');
    $servicio->asentar($segundo->id, -25, 'sancion_leve');

    $primero->update(['reputation_score' => 0]);
    $segundo->update(['reputation_score' => 0]);

    $recalculados = $servicio->recalcular();

    expect($recalculados)->toBe(2)
        ->and($primero->fresh()->reputation_score)->toBe(50)
        ->and($segundo->fresh()->reputation_score)->toBe(-25);
});

test('historial devuelve los asientos del ledger en orden inverso', function () {
    $usuario = User::factory()->create();
    $servicio = app(ReputationService::class);

    $this->travelTo(now());

    $servicio->asentar($usuario->id, 10, 'participacion');
    $this->travel(1)->minutes();
    $servicio->asentar($usuario->id, 50, 'reporte_validado');

    $historial = $servicio->historial($usuario);

    expect($historial)->toHaveCount(2)
        ->and($historial->first()->motivo)->toBe('reporte_validado')
        ->and($historial->last()->motivo)->toBe('participacion');
});

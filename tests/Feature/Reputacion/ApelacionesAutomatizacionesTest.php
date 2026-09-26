<?php

use App\Enums\EstadoApelacion;
use App\Enums\EstadoReporte;
use App\Enums\EstadoSancion;
use App\Enums\GravedadSancion;
use App\Models\Apelacion;
use App\Models\Empresa;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\Sancion;
use App\Models\User;
use App\Services\Reputacion\ReputationService;
use Illuminate\Support\Facades\Artisan;

test('automatizacion 1: pausa cautelar de suspension mientras una apelacion este pendiente', function () {
    $investigador = investigador();
    $servicio = app(ReputationService::class);

    // Sanción media: 7 días de suspensión
    $sancion = $servicio->aplicarSancion($investigador, 'falso_positivo', GravedadSancion::Media);

    // El usuario está suspendido
    expect(User::whereKey($investigador->id)->whereHas('sanciones', fn ($q) => $q->suspensionEnCurso())->exists())->toBeTrue();

    // El usuario apela la sanción
    $apelacion = $servicio->crearApelacion($sancion, $investigador, 'Apelación por error de apreciación.');

    // Con la apelación pendiente, la suspensión entra en pausa cautelar (no está activo el bloqueo)
    expect(User::whereKey($investigador->id)->whereHas('sanciones', fn ($q) => $q->suspensionEnCurso())->exists())->toBeFalse();

    // El administrador rechaza la apelación
    $admin = administrador();
    $servicio->resolverApelacion($apelacion, false, $admin, 'Rechazada tras análisis.');

    // La suspensión se reanuda por el período restante
    expect(User::whereKey($investigador->id)->whereHas('sanciones', fn ($q) => $q->suspensionEnCurso())->exists())->toBeTrue();
    expect($sancion->fresh()->estado)->toBe(EstadoSancion::Aplicada);
});

test('automatizacion 2: revocacion automatica en cascada cuando un reporte sancionado es validado', function () {
    $admin = administrador();
    $investigador = investigador();
    $empresa = Empresa::factory()->create();
    $programa = Programa::factory()->create(['estado' => 'activo', 'empresa_id' => $empresa->id]);
    $moderador = moderadorDe($programa);

    $reporte = reporteDe($investigador, $programa, [
        'estado' => EstadoReporte::Enviado->value,
        'asignado_a' => $moderador->id,
    ]);

    // El moderador rechaza el reporte y sanciona
    $this->actingAs($moderador)->post(route('reportes.rechazar', $reporte), [
        'nota' => 'Parecía fabricado.',
        'sancionar' => true,
        'gravedad_sancion' => 'media',
    ])->assertRedirect();

    $sancion = Sancion::where('reporte_id', $reporte->id)->firstOrFail();
    expect($sancion->estado)->toBe(EstadoSancion::Aplicada);

    // El investigador apela
    $this->actingAs($investigador)->post(route('reputacion.apelar', $sancion), [
        'motivo' => 'Adjunto comprobación adicional de que el bug es real.',
    ])->assertRedirect();

    $apelacion = Apelacion::where('sancion_id', $sancion->id)->firstOrFail();
    expect($apelacion->estado)->toBe(EstadoApelacion::Pendiente);

    // Posteriormente, tras revisión del informe, se valida
    $reporte->update(['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => $moderador->id]);
    $this->actingAs($moderador)->post(route('reportes.validar', $reporte))->assertRedirect();

    // En cascada: la sanción se revoca y la apelación se aprueba automáticamente
    expect($sancion->fresh()->estado)->toBe(EstadoSancion::Revocada)
        ->and($apelacion->fresh()->estado)->toBe(EstadoApelacion::Aprobada)
        ->and($apelacion->fresh()->nota_resolucion)->toContain('Revocada automáticamente');
});

test('automatizacion 3: SLA alerta al admin si una apelacion lleva mas de 48 horas sin resolver', function () {
    $admin = administrador();
    $investigador = investigador();
    $servicio = app(ReputationService::class);

    $sancion = $servicio->aplicarSancion($investigador, 'falso_positivo', GravedadSancion::Leve);
    $apelacion = $servicio->crearApelacion($sancion, $investigador, 'No hubo falsedad.');

    // Adelantamos el tiempo 50 horas
    $this->travelTo(now()->addHours(50));

    // Ejecutamos la auditoría de SLA
    Artisan::call('apelaciones:auditar-sla');

    // El administrador recibe la alerta de urgencia
    $notificaciones = $admin->fresh()->notifications()->get();
    expect($notificaciones->pluck('data.titulo'))->toContain('Apelación urgente sin resolver');
});

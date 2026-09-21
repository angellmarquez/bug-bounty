<?php

use App\Enums\GravedadSancion;
use App\Models\Programa;
use App\Services\Reputacion\ReputationService;

function programaParaReportar(): Programa
{
    return conObjetivo(Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]));
}

test('un investigador suspendido no puede presentar informes nuevos', function () {
    $programa = programaParaReportar();
    $inv = investigador();
    app(ReputationService::class)->aplicarSancion($inv, 'fabricacion', GravedadSancion::Media);
    $this->actingAs($inv);

    expect($inv->fresh()->suspensionActiva())->not->toBeNull();
    $this->post(route('reportes.store'), ['programa_id' => $programa->id, 'titulo' => 'Bloqueado', 'descripcion' => 'x'])->assertForbidden();
    $this->assertDatabaseMissing('reportes', ['titulo' => 'Bloqueado']);
});

test('un investigador suspendido tampoco puede enviar un borrador que ya tenía', function () {
    $inv = investigador();
    $borrador = reporteDe($inv, programaParaReportar(), ['estado' => 'borrador']);
    app(ReputationService::class)->aplicarSancion($inv, 'fabricacion', GravedadSancion::Grave);
    $this->actingAs($inv);

    $this->post(route('reportes.enviar', $borrador))->assertForbidden();
    expect($borrador->fresh()->estado->value)->toBe('borrador');
});

test('la sanción leve solo resta puntos y no suspende', function () {
    $programa = programaParaReportar();
    $inv = investigador();
    app(ReputationService::class)->aplicarSancion($inv, 'leve', GravedadSancion::Leve);
    $this->actingAs($inv);

    expect($inv->fresh()->suspensionActiva())->toBeNull();
    $this->post(route('reportes.store'), ['programa_id' => $programa->id, 'titulo' => 'Permitido', 'descripcion' => 'x'])->assertRedirect();
});

test('al terminar la suspensión el investigador vuelve a poder reportar', function () {
    $programa = programaParaReportar();
    $inv = investigador();
    app(ReputationService::class)->aplicarSancion($inv, 'fabricacion', GravedadSancion::Media);
    $this->actingAs($inv);
    $this->post(route('reportes.store'), ['programa_id' => $programa->id, 'titulo' => 'Aún no', 'descripcion' => 'x'])->assertForbidden();

    $this->travel(8)->days();

    expect($inv->fresh()->suspensionActiva())->toBeNull();
    $this->post(route('reportes.store'), ['programa_id' => $programa->id, 'titulo' => 'Ya sí', 'descripcion' => 'x'])->assertRedirect();
});

test('una sanción revocada deja de suspender', function () {
    $programa = programaParaReportar();
    $inv = investigador();
    $servicio = app(ReputationService::class);
    $sancion = $servicio->aplicarSancion($inv, 'fabricacion', GravedadSancion::Media);
    $servicio->revocarSancion($sancion, 'Error de moderación');
    $this->actingAs($inv);

    expect($inv->fresh()->suspensionActiva())->toBeNull();
    $this->post(route('reportes.store'), ['programa_id' => $programa->id, 'titulo' => 'Revocada', 'descripcion' => 'x'])->assertRedirect();
});

test('estar suspendido no impide entrar a la plataforma ni apelar', function () {
    $inv = investigador();
    $sancion = app(ReputationService::class)->aplicarSancion($inv, 'fabricacion', GravedadSancion::Media);
    $this->actingAs($inv);

    $this->get(route('dashboard'))->assertOk();
    $this->get(route('reputacion.ledger'))->assertOk();
    $this->post(route('reputacion.apelar', $sancion), ['motivo' => 'No fue justo.'])->assertRedirect();
});

<?php

use App\Models\EntradaReputacion;
use App\Models\Sancion;

test('la pagina del salon de la fama carga correctamente de forma publica', function () {
    $this->get(route('hall-of-fame'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('HallOfFame')
            ->has('ranking')
            ->has('metricas')
            ->where('periodo', 'historico'));
});

test('solo incluye investigadores en el ranking y los ordena por reputacion descendente', function () {
    $inv1 = investigador();
    $inv1->update(['reputation_score' => 150]);

    $inv2 = investigador();
    $inv2->update(['reputation_score' => 300]);

    $admin = administrador();
    $admin->update(['reputation_score' => 500]);

    $response = $this->get(route('hall-of-fame'));
    $response->assertOk();

    $ranking = $response->inertiaProps('ranking');
    $ids = collect($ranking)->pluck('id')->all();

    // El admin no debe estar aunque tenga puntos
    expect($ids)->not->toContain($admin->id);

    // inv2 debe estar antes que inv1 (300 > 150)
    expect($ids[0])->toBe($inv2->id)
        ->and($ids[1])->toBe($inv1->id);
});

test('los investigadores suspendidos se excluyen del salon de la fama', function () {
    $inv = investigador();
    $inv->update(['reputation_score' => 500]);

    // Aplicar suspensión activa
    Sancion::factory()->create([
        'usuario_id' => $inv->id,
        'estado' => 'aplicada',
        'suspension_desde' => now()->subDay(),
        'suspension_hasta' => now()->addDays(5),
    ]);

    $response = $this->get(route('hall-of-fame'));
    $ranking = $response->inertiaProps('ranking');
    $ids = collect($ranking)->pluck('id')->all();

    expect($ids)->not->toContain($inv->id);
});

test('el filtro mensual calcula correctamente los puntos del periodo actual', function () {
    $inv = investigador();
    $inv->update(['reputation_score' => 200]);

    // Asiento del mes pasado (100 pts)
    EntradaReputacion::factory()->create([
        'usuario_id' => $inv->id,
        'puntos' => 100,
        'motivo' => 'reporte_validado',
        'created_at' => now()->subMonth()->subDays(2),
    ]);

    // Asiento de este mes (100 pts)
    EntradaReputacion::factory()->create([
        'usuario_id' => $inv->id,
        'puntos' => 100,
        'motivo' => 'reporte_validado',
        'created_at' => now(),
    ]);

    $response = $this->get(route('hall-of-fame', ['periodo' => 'mensual']));
    $response->assertOk();

    $ranking = $response->inertiaProps('ranking');
    $hacker = collect($ranking)->firstWhere('id', $inv->id);

    expect($hacker['puntos'])->toBe(100);
});

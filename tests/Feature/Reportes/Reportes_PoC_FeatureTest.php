<?php

use App\Models\Programa;
use App\Models\Reporte;
use App\Services\Pgp\PgpService;

test('poc null is valid', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
        'poc' => null,
    ]);

    $this->assertDatabaseHas('reportes', [
        'investigador_id' => $user->id,
        'poc' => null,
    ]);
});

test('poc array is saved', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $poc = [
        'pasos' => '1. Navegar a /login. 2. Inyectar script',
        'evidencia' => 'xss payload here',
    ];

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
        'poc' => $poc,
    ]);

    $reporte = Reporte::where('investigador_id', $user->id)->first();
    $this->assertEquals($poc, app(PgpService::class)->descifrarReporte($reporte->descripcion, $reporte->poc)['poc']);
});

test('poc with multiple keys is saved', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $poc = [
        'endpoint' => 'https://api.example.com/v1/users',
        'metodo' => 'POST',
        'payload' => '{"name": "test"}',
        'headers' => 'Content-Type: application/json',
    ];

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
        'poc' => $poc,
    ]);

    $reporte = Reporte::where('investigador_id', $user->id)->first();
    $this->assertEquals('https://api.example.com/v1/users', pocDe($reporte)['endpoint']);
    $this->assertEquals('POST', pocDe($reporte)['metodo']);
});

test('poc string values are saved', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
        'poc' => ['simple' => 'just a string value'],
    ]);

    $reporte = Reporte::where('investigador_id', $user->id)->first();
    $this->assertEquals('just a string value', pocDe($reporte)['simple']);
});

test('poc nested array is saved', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $paso1 = ['accion' => 'Abrir navegador', 'detalle' => 'Ir a la URL'];
    $paso2 = ['accion' => 'Inyectar', 'detalle' => 'XSS en campo'];
    $poc = ['pasos' => [$paso1, $paso2]];

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
        'poc' => $poc,
    ]);

    $reporte = Reporte::where('investigador_id', $user->id)->first();
    $this->assertCount(2, pocDe($reporte)['pasos']);
});

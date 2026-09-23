<?php

use App\Models\Programa;

test('investigador can enviar own borrador con poc', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, [
        'estado' => 'borrador',
        'poc' => pocCifrado(['evidencia' => 'Paso 1: abrir /login. Paso 2: inyectar payload.']),
    ]);

    $response = $this->post(route('reportes.enviar', $reporte));

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'id' => $reporte->id,
        'estado' => 'enviado',
    ]);
    $this->assertNotNull($reporte->fresh()->enviado_en);
});

test('enviado event is created', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, [
        'estado' => 'borrador',
        'poc' => pocCifrado(['evidencia' => 'Paso 1: abrir /login. Paso 2: inyectar payload.']),
    ]);

    $this->post(route('reportes.enviar', $reporte));

    $this->assertDatabaseHas('eventos_reporte', [
        'reporte_id' => $reporte->id,
        'tipo' => 'enviado',
    ]);
});

test('cannot enviar borrador sin poc: todo programa la exige', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'borrador', 'poc' => null]);

    $response = $this->post(route('reportes.enviar', $reporte));

    $response->assertRedirect(route('reportes.show', $reporte));
    $response->assertSessionHasErrors('poc');
    $this->assertDatabaseHas('reportes', [
        'id' => $reporte->id,
        'estado' => 'borrador',
    ]);
});

test('cannot enviar borrador con poc requerida del schema incompleta', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create([
        'poc_schema' => [
            ['name' => 'url', 'label' => 'URL afectada', 'type' => 'url', 'required' => true],
            ['name' => 'pasos', 'label' => 'Pasos para reproducir', 'type' => 'textarea', 'required' => true],
        ],
    ]);

    // Solo se completó "url": falta el campo obligatorio "pasos".
    $reporte = reporteDe($user, $programa, [
        'estado' => 'borrador',
        'poc' => pocCifrado(['url' => 'https://ejemplo.com/ruta']),
    ]);

    $response = $this->post(route('reportes.enviar', $reporte));

    $response->assertSessionHasErrors('poc');
    $this->assertDatabaseHas('reportes', [
        'id' => $reporte->id,
        'estado' => 'borrador',
    ]);
});

test('cannot enviar already enviado reporte', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'enviado']);

    $response = $this->post(route('reportes.enviar', $reporte));

    $response->assertForbidden();
});

test('cannot enviar reporte of another', function () {
    $user = investigador();
    $this->actingAs($user);

    $otro = investigador();
    $reporteAjeno = reporteDe($otro, null, ['estado' => 'borrador']);

    $response = $this->post(route('reportes.enviar', $reporteAjeno));

    $response->assertForbidden();
});

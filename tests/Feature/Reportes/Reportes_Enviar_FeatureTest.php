<?php

test('investigador can enviar own borrador', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'borrador']);

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

    $reporte = reporteDe($user, null, ['estado' => 'borrador']);

    $this->post(route('reportes.enviar', $reporte));

    $this->assertDatabaseHas('eventos_reporte', [
        'reporte_id' => $reporte->id,
        'tipo' => 'enviado',
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

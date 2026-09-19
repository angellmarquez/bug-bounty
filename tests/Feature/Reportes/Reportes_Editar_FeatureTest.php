<?php

test('investigador can edit own borrador', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'borrador']);

    $response = $this->put(route('reportes.update', $reporte), [
        'titulo' => 'Titulo actualizado',
        'descripcion' => 'Descripcion actualizada',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'id' => $reporte->id,
        'titulo' => 'Titulo actualizado',
    ]);
});

test('investigador can edit enviado limited fields', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'enviado']);

    $response = $this->put(route('reportes.update', $reporte), [
        'titulo' => 'Titulo editado',
        'descripcion' => 'Nueva descripcion',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'id' => $reporte->id,
        'titulo' => 'Titulo editado',
    ]);
});

test('investigador cannot edit reporte of another', function () {
    $user = investigador();
    $this->actingAs($user);

    $otro = investigador();
    $reporteAjeno = reporteDe($otro, null, ['estado' => 'borrador']);

    $response = $this->put(route('reportes.update', $reporteAjeno), [
        'titulo' => 'Hackeado',
    ]);

    $response->assertForbidden();
});

test('gestion cannot edit reportes', function () {
    $user = gestion();
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

    $response = $this->put(route('reportes.update', $reporte), [
        'titulo' => 'Modificado por gestion',
    ]);

    $response->assertForbidden();
});

test('closed reporte cannot be edited', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'cerrado']);

    $response = $this->put(route('reportes.update', $reporte), [
        'titulo' => 'Intento editar cerrado',
    ]);

    $response->assertForbidden();
});

test('poc update works correctly', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, [
        'estado' => 'borrador',
        'poc' => ['old_key' => 'old_value'],
    ]);

    $this->put(route('reportes.update', $reporte), [
        'poc' => ['new_key' => 'new_value', 'extra' => 'data'],
    ]);

    $reporte->refresh();
    $this->assertEquals('new_value', $reporte->poc['new_key']);
    $this->assertEquals('data', $reporte->poc['extra']);
});

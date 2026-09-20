<?php

use App\Models\Programa;

test('gestion can change estado from borrador to activo', function () {
    $user = gestion();
    $this->actingAs($user);

    $programa = programaDe($user, ['estado' => 'borrador']);

    $response = $this->post(route('programas.cambiar-estado', $programa), [
        'estado' => 'activo',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('programas', ['id' => $programa->id, 'estado' => 'activo']);
});

test('gestion can change activo to en_pausa', function () {
    $user = gestion();
    $this->actingAs($user);

    $programa = programaDe($user, ['estado' => 'activo']);

    $response = $this->post(route('programas.cambiar-estado', $programa), [
        'estado' => 'en_pausa',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('programas', ['id' => $programa->id, 'estado' => 'en_pausa']);
});

test('gestion can change en_pausa back to activo', function () {
    $user = gestion();
    $this->actingAs($user);

    $programa = programaDe($user, ['estado' => 'en_pausa']);

    $response = $this->post(route('programas.cambiar-estado', $programa), [
        'estado' => 'activo',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('programas', ['id' => $programa->id, 'estado' => 'activo']);
});

test('cannot change archivado to any state', function () {
    $user = gestion();
    $this->actingAs($user);

    $programa = programaDe($user, ['estado' => 'archivado']);

    $response = $this->post(route('programas.cambiar-estado', $programa), [
        'estado' => 'activo',
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseHas('programas', ['id' => $programa->id, 'estado' => 'archivado']);
});

test('cannot skip states from borrador to en_pausa', function () {
    $user = gestion();
    $this->actingAs($user);

    $programa = programaDe($user, ['estado' => 'borrador']);

    $response = $this->post(route('programas.cambiar-estado', $programa), [
        'estado' => 'en_pausa',
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseHas('programas', ['id' => $programa->id, 'estado' => 'borrador']);
});

test('investigador cannot change estado', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create(['estado' => 'activo']);

    $response = $this->post(route('programas.cambiar-estado', $programa), [
        'estado' => 'en_pausa',
    ]);

    $response->assertForbidden();
});

test('admin can change estado of any program', function () {
    $user = administrador();
    $this->actingAs($user);

    $programa = programaDe(gestion(), ['estado' => 'borrador']);

    $response = $this->post(route('programas.cambiar-estado', $programa), [
        'estado' => 'activo',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('programas', ['id' => $programa->id, 'estado' => 'activo']);
});

test('estado change is recorded in program', function () {
    $user = gestion();
    $this->actingAs($user);

    $programa = programaDe($user, ['estado' => 'borrador']);

    $this->post(route('programas.cambiar-estado', $programa), [
        'estado' => 'activo',
    ]);

    $programa->refresh();
    $this->assertEquals('activo', $programa->estado->value);
});

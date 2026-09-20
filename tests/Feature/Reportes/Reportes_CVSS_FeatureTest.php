<?php

use App\Models\Programa;

test('cvss vector is saved correctly', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test CVSS',
        'descripcion' => 'Test desc',
        'vector_cvss' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H',
    ]);

    $this->assertDatabaseHas('reportes', [
        'investigador_id' => $user->id,
        'vector_cvss' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H',
    ]);
});

test('puntuacion_cvss is saved with 1 decimal', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
        'puntuacion_cvss' => 4.3,
    ]);

    $this->assertDatabaseHas('reportes', [
        'investigador_id' => $user->id,
        'puntuacion_cvss' => 4.3,
    ]);
});

test('severidad enum is saved correctly', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
        'severidad' => 'alta',
    ]);

    $this->assertDatabaseHas('reportes', [
        'investigador_id' => $user->id,
        'severidad' => 'alta',
    ]);
});

test('vector_cvss max 100 chars validation', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $response = $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
        'vector_cvss' => str_repeat('A', 101),
    ]);

    $response->assertSessionHasErrors('vector_cvss');
});

test('severidad can be null', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
        'severidad' => null,
    ]);

    $this->assertDatabaseHas('reportes', [
        'investigador_id' => $user->id,
        'severidad' => null,
    ]);
});

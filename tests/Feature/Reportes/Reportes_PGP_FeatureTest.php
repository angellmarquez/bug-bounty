<?php

use App\Models\ClavePgp;
use App\Models\Programa;
use App\Models\Reporte;

test('without pgp key description is plain text', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Esta es una descripcion normal sin cifrar',
    ]);

    $this->assertDatabaseHas('reportes', [
        'investigador_id' => $user->id,
        'descripcion' => 'Esta es una descripcion normal sin cifrar',
    ]);
});

test('with pgp key description is encrypted', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $clave = ClavePgp::factory()->create([
        'usuario_id' => $user->id,
        'estado' => 'activa',
    ]);

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test PGP',
        'descripcion' => 'Descripcion sensible que debe cifrarse',
        'clave_pgp_id' => $clave->id,
    ]);

    $reporte = Reporte::where('investigador_id', $user->id)->first();
    $this->assertNotEquals(
        'Descripcion sensible que debe cifrarse',
        $reporte->descripcion
    );
});

test('nonexistent pgp key returns 422', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $response = $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
        'clave_pgp_id' => 99999,
    ]);

    $response->assertSessionHasErrors('clave_pgp_id');
});

test('another user pgp key is rejected', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $otroUsuario = investigador();
    $claveAjena = ClavePgp::factory()->create([
        'usuario_id' => $otroUsuario->id,
        'estado' => 'activa',
    ]);

    $response = $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
        'clave_pgp_id' => $claveAjena->id,
    ]);

    $response->assertStatus(403);
});

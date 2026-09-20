<?php

use App\Models\Programa;

test('administrator can assign moderator to a program', function () {
    $admin = administrador();
    $moderador = investigador();
    $moderador->roles()->syncWithoutDetaching([rol('moderador')->id]);
    $programa = Programa::factory()->create();
    $this->actingAs($admin);

    $this->post(route('admin.programas.moderadores.asignar', [$programa, $moderador]))
        ->assertRedirect(route('admin.moderadores'));

    expect($programa->fresh()->moderadores()->whereKey($moderador->id)->exists())->toBeTrue();
});

test('moderator sees reports from every non draft program', function () {
    $moderador = investigador();
    $moderador->roles()->syncWithoutDetaching([rol('moderador')->id]);
    $asignado = Programa::factory()->create();
    $noAsignado = Programa::factory()->create();
    $asignado->moderadores()->attach($moderador);
    reporteDe(investigador(), $asignado, ['estado' => 'enviado']);
    $oculto = reporteDe(investigador(), $noAsignado, ['estado' => 'enviado']);
    $this->actingAs($moderador);

    $response = $this->get(route('reportes.index'));
    $ids = collect($response->inertiaProps()['reportes']['data'])->pluck('id');

    expect($ids)->toContain($oculto->id)->and($ids)->not->toBeEmpty();
});

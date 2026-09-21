<?php

use App\Models\Programa;
use App\Models\User;

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

test('moderator sees only the reports of the programs assigned to them', function () {
    $moderador = User::factory()->create();
    $moderador->roles()->syncWithoutDetaching([rol('moderador')->id]);
    $asignado = Programa::factory()->create();
    $noAsignado = Programa::factory()->create();
    $asignado->moderadores()->attach($moderador);
    $visible = reporteDe(investigador(), $asignado, ['estado' => 'enviado']);
    $borrador = reporteDe(investigador(), $asignado, ['estado' => 'borrador']);
    $oculto = reporteDe(investigador(), $noAsignado, ['estado' => 'enviado']);
    $this->actingAs($moderador);

    $ids = collect($this->get(route('reportes.index'))->inertiaProps()['reportes']['data'])->pluck('id');

    expect($ids)->toContain($visible->id)->not->toContain($oculto->id)->not->toContain($borrador->id);
    $this->get(route('reportes.show', $oculto))->assertForbidden();
    $this->get(route('reportes.show', $borrador))->assertForbidden();
});

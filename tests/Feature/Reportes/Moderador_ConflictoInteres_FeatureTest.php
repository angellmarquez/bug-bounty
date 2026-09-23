<?php

use App\Models\Programa;
use App\Models\User;

function moderadorInvestigador(Programa ...$programas): User
{
    $usuario = conRol(User::factory()->create(), ['investigador', 'moderador']);
    $usuario->programasModerados()->attach(array_map(fn (Programa $programa) => $programa->id, $programas));

    return $usuario;
}

function programaAbierto(array $atributos = []): Programa
{
    return conObjetivo(Programa::factory()->create(['estado' => 'activo', 'es_publico' => true, ...$atributos]));
}

test('un moderador que también es investigador no puede reportar en el programa que modera', function () {
    $moderado = programaAbierto();
    $this->actingAs(moderadorInvestigador($moderado));

    $this->post(route('reportes.store'), ['programa_id' => $moderado->id, 'titulo' => 'Trampa', 'descripcion' => 'x'])->assertForbidden();

    $this->assertDatabaseMissing('reportes', ['titulo' => 'Trampa']);
});

test('ese mismo usuario tampoco reporta en los programas que no modera: un moderador nunca reporta', function () {
    $moderado = programaAbierto();
    $otro = programaAbierto();
    $this->actingAs(moderadorInvestigador($moderado));

    $this->post(route('reportes.store'), ['programa_id' => $otro->id, 'titulo' => 'Tampoco', 'descripcion' => 'x'])->assertForbidden();

    $this->assertDatabaseMissing('reportes', ['titulo' => 'Tampoco']);
});

test('el formulario de reporte no ofrece los programas que el usuario modera', function () {
    $moderado = programaAbierto();
    $otro = programaAbierto();
    $this->actingAs(moderadorInvestigador($moderado));

    $ids = collect($this->get(route('reportes.create'))->inertiaProps()['programas'])->pluck('id')->all();

    expect($ids)->toContain($otro->id)->not->toContain($moderado->id);
});

test('la página del programa avisa que lo modera y no ofrece reportar', function () {
    $moderado = programaAbierto();
    $otro = programaAbierto();
    $this->actingAs(moderadorInvestigador($moderado));

    $this->get(route('programas.show', $moderado))
        ->assertInertia(fn ($page) => $page->where('moderaEstePrograma', true)->where('puedeReportar', false)->where('puedeModerar', true));
    $this->get(route('programas.show', $otro))
        ->assertInertia(fn ($page) => $page->where('moderaEstePrograma', false)->where('puedeReportar', false)->where('puedeModerar', false));
});

test('un moderador investigador sigue viendo sus informes antiguos pero ya no los envía', function () {
    $otro = programaAbierto();
    $usuario = moderadorInvestigador(programaAbierto());
    $propio = reporteDe($usuario, $otro, [
        'estado' => 'borrador',
        'poc' => pocCifrado(['evidencia' => 'Evidencia de prueba.']),
    ]);
    $this->actingAs($usuario);

    $this->get(route('reportes.show', $propio))->assertOk();
    $this->post(route('reportes.enviar', $propio))->assertForbidden();
    expect($propio->fresh()->estado->value)->toBe('borrador');
});

test('no se puede asignar como moderador de un programa a quien ya reportó en él', function () {
    $programa = programaAbierto();
    $usuario = conRol(User::factory()->create(), ['investigador', 'moderador']);
    reporteDe($usuario, $programa, ['estado' => 'enviado']);
    $this->actingAs(administrador());

    $this->post(route('admin.programas.moderadores.asignar', [$programa, $usuario]))->assertSessionHas('error');

    expect($programa->moderadores()->whereKey($usuario->id)->exists())->toBeFalse();
});

test('asignar al moderador a un programa donde no reportó funciona', function () {
    $programa = programaAbierto();
    $usuario = conRol(User::factory()->create(), ['investigador', 'moderador']);
    $this->actingAs(administrador());

    $this->post(route('admin.programas.moderadores.asignar', [$programa, $usuario]))->assertRedirect()->assertSessionMissing('error');

    expect($programa->moderadores()->whereKey($usuario->id)->exists())->toBeTrue();
});

test('nadie revisa su propio informe, ni siquiera un administrador', function () {
    $programa = programaAbierto();
    $ajeno = programaAbierto();
    $moderador = moderadorInvestigador($programa, $ajeno);
    $admin = administrador();
    $delModerador = reporteDe($moderador, $ajeno, ['estado' => 'enviado']);
    $delAdmin = reporteDe($admin, $programa, ['estado' => 'enviado']);

    $this->actingAs($moderador)->post(route('reportes.validar', $delModerador))->assertForbidden();
    $this->actingAs($admin)->post(route('reportes.validar', $delAdmin))->assertForbidden();

    expect($delModerador->fresh()->estado->value)->toBe('enviado')->and($delAdmin->fresh()->estado->value)->toBe('enviado');
});

test('un informe solo se asigna a moderadores de su programa que no sean su autor', function () {
    $programa = programaAbierto();
    $autor = investigador();
    $reporte = reporteDe($autor, $programa, ['estado' => 'enviado']);
    $delPrograma = moderadorDe($programa);
    $ajeno = moderadorDe(programaAbierto());
    $this->actingAs($delPrograma);

    $this->post(route('reportes.asignar', $reporte), ['asignado_a' => $ajeno->id])->assertSessionHasErrors('asignado_a');
    $this->post(route('reportes.asignar', $reporte), ['asignado_a' => $autor->id])->assertSessionHasErrors('asignado_a');
    $this->post(route('reportes.asignar', $reporte), ['asignado_a' => $delPrograma->id])->assertSessionHasNoErrors();

    expect($reporte->fresh()->asignado_a)->toBe($delPrograma->id);
});

test('la lista de candidatos a revisar solo incluye moderadores del programa', function () {
    $programa = programaAbierto();
    $reporte = reporteDe(investigador(), $programa, ['estado' => 'enviado']);
    $suyo = moderadorDe($programa, ['name' => 'Suyo']);
    moderadorDe(programaAbierto(), ['name' => 'Ajeno']);
    $this->actingAs($suyo);

    $nombres = collect($this->get(route('reportes.show', $reporte))->inertiaProps()['moderadoresAsignables'])->pluck('name')->all();

    expect($nombres)->toBe(['Suyo']);
});

test('revocar el rol de moderador también retira sus programas asignados', function () {
    $programa = programaAbierto();
    $moderador = moderadorDe($programa);
    $this->actingAs(administrador());

    $this->delete(route('admin.moderadores.revocar', $moderador))->assertRedirect();

    expect($moderador->programasModerados()->count())->toBe(0);
});

test('un moderador ya no ve la cola ni los informes de programas que no modera', function () {
    $suyo = programaAbierto();
    $ajeno = programaAbierto();
    $enElSuyo = reporteDe(investigador(), $suyo, ['estado' => 'enviado']);
    $enElAjeno = reporteDe(investigador(), $ajeno, ['estado' => 'enviado']);
    $this->actingAs(moderadorDe($suyo));

    $this->get(route('reportes.show', $enElSuyo))->assertOk();
    $this->get(route('reportes.show', $enElAjeno))->assertForbidden();
    $this->getJson(route('reportes.vista-rapida', $enElAjeno))->assertForbidden();
    $this->post(route('reportes.revisar', $enElAjeno))->assertForbidden();
});

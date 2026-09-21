<?php

use App\Models\Empresa;
use App\Models\Programa;
use App\Models\User;

test('moderador can asignar reporte enviado', function () {
    $programaModerado = Programa::factory()->create();
    $user = moderadorDe($programaModerado);
    $this->actingAs($user);

    $analista = moderadorDe($programaModerado);
    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado']);

    $response = $this->post(route('reportes.asignar', $reporte), [
        'asignado_a' => $analista->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'id' => $reporte->id,
        'asignado_a' => $analista->id,
    ]);
});

test('asignar creates asignacion event', function () {
    $programaModerado = Programa::factory()->create();
    $user = moderadorDe($programaModerado);
    $this->actingAs($user);

    $analista = moderadorDe($programaModerado);
    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado']);

    $this->post(route('reportes.asignar', $reporte), [
        'asignado_a' => $analista->id,
    ]);

    $this->assertDatabaseHas('eventos_reporte', [
        'reporte_id' => $reporte->id,
        'tipo' => 'asignacion',
        'actor_id' => $user->id,
    ]);
});

test('moderador can validate reporte enviado', function () {
    $programaModerado = Programa::factory()->create();
    $user = moderadorDe($programaModerado);
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado']);

    $response = $this->post(route('reportes.validar', $reporte));

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'id' => $reporte->id,
        'estado' => 'validado',
    ]);
});

test('validar creates cambio_estado event', function () {
    $programaModerado = Programa::factory()->create();
    $user = moderadorDe($programaModerado);
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado']);

    $this->post(route('reportes.validar', $reporte));

    $this->assertDatabaseHas('eventos_reporte', [
        'reporte_id' => $reporte->id,
        'tipo' => 'cambio_estado',
    ]);
});

test('moderador can rechazar reporte with nota', function () {
    $programaModerado = Programa::factory()->create();
    $user = moderadorDe($programaModerado);
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado']);

    $response = $this->post(route('reportes.rechazar', $reporte), [
        'nota' => 'No cumple con los criterios del programa.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'id' => $reporte->id,
        'estado' => 'rechazado',
    ]);
    $this->assertDatabaseHas('eventos_reporte', [
        'reporte_id' => $reporte->id,
        'tipo' => 'cambio_estado',
        'nota' => 'No cumple con los criterios del programa.',
    ]);
});

test('moderador can mark reporte as duplicado', function () {
    $programaModerado = Programa::factory()->create();
    $user = moderadorDe($programaModerado);
    $this->actingAs($user);

    $original = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado']);
    $duplicado = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado']);

    $response = $this->post(route('reportes.marcar-duplicado', $duplicado), [
        'reporte_duplicado_id' => $original->id,
        'nota' => 'Es duplicado de otro reporte.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'id' => $duplicado->id,
        'estado' => 'duplicado',
        'es_duplicado_de' => $original->id,
    ]);
    $this->assertDatabaseHas('eventos_reporte', [
        'reporte_id' => $duplicado->id,
        'tipo' => 'marcado_duplicado',
    ]);
});

test('empresa owner can marcar en reparacion un reporte validado', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));

    $reporte = reporteDe(investigador(), $programa, ['estado' => 'validado']);

    $this->post(route('reportes.reparacion', $reporte))->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('reportes', ['id' => $reporte->id, 'estado' => 'en_reparacion']);
    $this->assertDatabaseHas('eventos_reporte', ['reporte_id' => $reporte->id, 'tipo' => 'cambio_estado']);
});

test('empresa owner can cerrar reporte', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));

    $reporte = reporteDe(investigador(), $programa, ['estado' => 'en_reparacion']);

    $response = $this->post(route('reportes.cerrar', $reporte));

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'id' => $reporte->id,
        'estado' => 'cerrado',
    ]);
    $this->assertNotNull($reporte->fresh()->cerrado_en);
});

test('anyone can add comment to reporte they can view', function () {
    $programaModerado = Programa::factory()->create();
    $user = moderadorDe($programaModerado);
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado']);

    $response = $this->post(route('reportes.comentar', $reporte), [
        'nota' => 'Comentario de revision.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('eventos_reporte', [
        'reporte_id' => $reporte->id,
        'tipo' => 'comentario',
        'nota' => 'Comentario de revision.',
    ]);
});

test('investigador cannot triaje reporte', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'enviado']);

    $response = $this->post(route('reportes.validar', $reporte));
    $response->assertForbidden();
});

test('investigador cannot asignar reporte', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'enviado']);

    $response = $this->post(route('reportes.asignar', $reporte), [
        'asignado_a' => $user->id,
    ]);
    $response->assertForbidden();
});

test('investigador cannot rechazar reporte', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'enviado']);

    $response = $this->post(route('reportes.rechazar', $reporte), [
        'nota' => 'Test',
    ]);
    $response->assertForbidden();
});

test('investigador cannot marcar en reparacion su reporte', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'validado']);

    $this->post(route('reportes.reparacion', $reporte))->assertForbidden();
    expect($reporte->fresh()->estado->value)->toBe('validado');
});

test('investigador cannot cerrar reporte', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'validado']);

    $response = $this->post(route('reportes.cerrar', $reporte));
    $response->assertForbidden();
});

test('moderador cannot validate reporte already validado', function () {
    $programaModerado = Programa::factory()->create();
    $user = moderadorDe($programaModerado);
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'validado']);

    $this->post(route('reportes.validar', $reporte))->assertSessionHasErrors('estado');
    expect($reporte->fresh()->estado->value)->toBe('validado');
});

test('un informe validado se puede reparar y cerrar sin errores', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));
    $reporte = reporteDe(investigador(), $programa, ['estado' => 'validado']);

    $this->post(route('reportes.reparacion', $reporte))->assertRedirect()->assertSessionHasNoErrors();
    expect($reporte->fresh()->estado->value)->toBe('en_reparacion');

    $this->post(route('reportes.cerrar', $reporte))->assertRedirect()->assertSessionHasNoErrors();
    expect($reporte->fresh()->estado->value)->toBe('cerrado');
});

test('un informe validado se puede cerrar directamente', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));
    $reporte = reporteDe(investigador(), $programa, ['estado' => 'validado']);

    $this->post(route('reportes.cerrar', $reporte))->assertRedirect()->assertSessionHasNoErrors();
    expect($reporte->fresh()->estado->value)->toBe('cerrado');
});

test('la pagina solo ofrece las acciones validas para el estado actual', function (string $estado, array $esperadas) {
    $programaModerado = Programa::factory()->create();
    $this->actingAs(moderadorDe($programaModerado));
    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => $estado]);

    $acciones = $this->get(route('reportes.show', $reporte))->inertiaProps()['accionesDisponibles'];

    foreach ($esperadas as $accion => $disponible) {
        expect($acciones[$accion])->toBe($disponible, "{$estado}: {$accion}");
    }
})->with([
    'enviado' => ['enviado', ['revisar' => true, 'validar' => true, 'rechazar' => true, 'reparacion' => false, 'cerrar' => false]],
    'en_revision' => ['en_revision', ['revisar' => false, 'validar' => true, 'rechazar' => true, 'reparacion' => false, 'cerrar' => false]],
    // El moderador solo decide si el informe es válido, duplicado o no válido: no repara ni cierra.
    'validado' => ['validado', ['revisar' => false, 'validar' => false, 'rechazar' => true, 'reparacion' => false, 'cerrar' => false]],
    'en_reparacion' => ['en_reparacion', ['validar' => false, 'reparacion' => false, 'cerrar' => false]],
]);

test('la empresa duena del programa solo puede marcar en reparacion y cerrar', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));
    $reporte = reporteDe(investigador(), $programa, ['estado' => 'validado']);

    $acciones = $this->get(route('reportes.show', $reporte))->inertiaProps()['accionesDisponibles'];

    expect($acciones)->toMatchArray([
        'revisar' => false,
        'validar' => false,
        'rechazar' => false,
        'marcar_duplicado' => false,
        'asignar' => false,
        'reparacion' => true,
        'cerrar' => true,
    ]);
});

test('el moderador no puede marcar en reparacion ni cerrar informes', function () {
    $programaModerado = Programa::factory()->create();
    $this->actingAs(moderadorDe($programaModerado));
    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'validado']);

    $this->post(route('reportes.reparacion', $reporte))->assertForbidden();
    $this->post(route('reportes.cerrar', $reporte))->assertForbidden();
    expect($reporte->fresh()->estado->value)->toBe('validado');
});

test('el administrador puede cerrar informes de cualquier programa', function () {
    $this->actingAs(administrador());
    $reporte = reporteDe(investigador(), null, ['estado' => 'validado']);

    $this->post(route('reportes.cerrar', $reporte))->assertRedirect();
    expect($reporte->fresh()->estado->value)->toBe('cerrado');
});

test('una empresa ajena no puede cerrar el informe', function () {
    $duena = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $duena->id]);
    $reporte = reporteDe(investigador(), $programa, ['estado' => 'validado']);
    $this->actingAs(miembroDeEmpresa(Empresa::factory()->aprobada()->create()));

    $this->post(route('reportes.cerrar', $reporte))->assertForbidden();
    expect($reporte->fresh()->estado->value)->toBe('validado');
});

test('gestion cannot triaje reportes it cannot access', function (User $usuario) {
    $this->actingAs($usuario);

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

    $this->post(route('reportes.validar', $reporte))->assertForbidden();
    $this->post(route('reportes.comentar', $reporte), ['nota' => 'hola'])->assertForbidden();
    $this->assertDatabaseHas('reportes', ['id' => $reporte->id, 'estado' => 'enviado']);
})->with([
    'gestion' => fn () => gestion(),
]);

test('admin can triaje reportes de cualquier programa', function () {
    $this->actingAs(administrador());

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

    $this->post(route('reportes.validar', $reporte))->assertRedirect();
    $this->assertDatabaseHas('reportes', ['id' => $reporte->id, 'estado' => 'validado']);
});

test('empresa member can read but not triaje reportes of its programas', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));

    $reporte = reporteDe(investigador(), $programa, ['estado' => 'enviado']);

    $this->get(route('reportes.show', $reporte))->assertOk();
    $this->post(route('reportes.validar', $reporte))->assertForbidden();
    $this->assertDatabaseHas('reportes', ['id' => $reporte->id, 'estado' => 'enviado']);
});

test('show page passes triaje props for moderador', function () {
    $programaModerado = Programa::factory()->create();
    $user = moderadorDe($programaModerado);
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado']);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertTrue($props['puedeTriar']);
    $this->assertTrue($props['accionesDisponibles']['asignar']);
    $this->assertTrue($props['accionesDisponibles']['validar']);
});

test('show page does not pass triaje for investigador', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'enviado']);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertFalse($props['puedeTriar']);
});

test('validar suma al investigador los puntos de reputación del evento reporte_validado', function () {
    $programaModerado = Programa::factory()->create();
    $this->actingAs(moderadorDe($programaModerado));
    $investigador = investigador();
    $reporte = reporteDe($investigador, $programaModerado, ['estado' => 'enviado']);

    $this->post(route('reportes.validar', $reporte))->assertRedirect();

    expect($investigador->fresh()->reputation_score)->toBe((int) config('reputacion.puntos.reporte_validado'));
    $this->assertDatabaseHas('ledger_reputacion', [
        'usuario_id' => $investigador->id,
        'reporte_id' => $reporte->id,
        'motivo' => 'reporte_validado',
    ]);
});

test('cerrar un informe suma al investigador los puntos de reputación del evento reporte_resuelto', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));
    $investigador = investigador();
    $reporte = reporteDe($investigador, $programa, ['estado' => 'validado']);

    $this->post(route('reportes.cerrar', $reporte))->assertRedirect();

    expect($investigador->fresh()->reputation_score)->toBe((int) config('reputacion.puntos.reporte_resuelto'));
    $this->assertDatabaseHas('ledger_reputacion', [
        'usuario_id' => $investigador->id,
        'reporte_id' => $reporte->id,
        'motivo' => 'reporte_resuelto',
    ]);
});

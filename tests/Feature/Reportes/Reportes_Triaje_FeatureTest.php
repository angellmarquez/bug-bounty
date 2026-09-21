<?php

use App\Models\Empresa;
use App\Models\Programa;
use App\Models\User;

test('moderador can asignar reporte enviado', function () {
    $user = moderador();
    $this->actingAs($user);

    $analista = moderador();
    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

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
    $user = moderador();
    $this->actingAs($user);

    $analista = moderador();
    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

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
    $user = moderador();
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

    $response = $this->post(route('reportes.validar', $reporte));

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'id' => $reporte->id,
        'estado' => 'validado',
    ]);
});

test('validar creates cambio_estado event', function () {
    $user = moderador();
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

    $this->post(route('reportes.validar', $reporte));

    $this->assertDatabaseHas('eventos_reporte', [
        'reporte_id' => $reporte->id,
        'tipo' => 'cambio_estado',
    ]);
});

test('moderador can rechazar reporte with nota', function () {
    $user = moderador();
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

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
    $user = moderador();
    $this->actingAs($user);

    $original = reporteDe(investigador(), null, ['estado' => 'enviado']);
    $duplicado = reporteDe(investigador(), null, ['estado' => 'enviado']);

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

test('empresa owner can pay reporte with recompensa', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));

    $reporte = reporteDe(investigador(), $programa, ['estado' => 'pago_pendiente', 'moneda' => 'USD']);

    $response = $this->post(route('reportes.pagar', $reporte), [
        'recompensa' => 500.00,
        'nota' => 'Recompensa transferida.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'id' => $reporte->id,
        'estado' => 'pagado',
        'recompensa' => 500.00,
    ]);
    $this->assertDatabaseHas('eventos_reporte', [
        'reporte_id' => $reporte->id,
        'tipo' => 'pago',
    ]);
});

test('empresa owner can cerrar reporte', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));

    $reporte = reporteDe(investigador(), $programa, ['estado' => 'pagado']);

    $response = $this->post(route('reportes.cerrar', $reporte));

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'id' => $reporte->id,
        'estado' => 'cerrado',
    ]);
    $this->assertNotNull($reporte->fresh()->cerrado_en);
});

test('anyone can add comment to reporte they can view', function () {
    $user = moderador();
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

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

test('investigador cannot pagar reporte', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'pago_pendiente']);

    $response = $this->post(route('reportes.pagar', $reporte), [
        'recompensa' => 100,
    ]);
    $response->assertForbidden();
});

test('investigador cannot cerrar reporte', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, ['estado' => 'pagado']);

    $response = $this->post(route('reportes.cerrar', $reporte));
    $response->assertForbidden();
});

test('moderador cannot validate reporte already validado', function () {
    $user = moderador();
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), null, ['estado' => 'validado']);

    $this->post(route('reportes.validar', $reporte))->assertSessionHasErrors('estado');
    expect($reporte->fresh()->estado->value)->toBe('validado');
});

test('un informe validado se puede pagar y cerrar sin errores', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));
    $reporte = reporteDe(investigador(), $programa, ['estado' => 'validado']);

    $this->post(route('reportes.pagar', $reporte), ['recompensa' => 500])->assertRedirect()->assertSessionHasNoErrors();
    expect($reporte->fresh()->estado->value)->toBe('pagado');

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
    $this->actingAs(moderador());
    $reporte = reporteDe(investigador(), null, ['estado' => $estado]);

    $acciones = $this->get(route('reportes.show', $reporte))->inertiaProps()['accionesDisponibles'];

    foreach ($esperadas as $accion => $disponible) {
        expect($acciones[$accion])->toBe($disponible, "{$estado}: {$accion}");
    }
})->with([
    'enviado' => ['enviado', ['revisar' => true, 'validar' => true, 'rechazar' => true, 'pagar' => false, 'cerrar' => false]],
    'en_revision' => ['en_revision', ['revisar' => false, 'validar' => true, 'rechazar' => true, 'pagar' => false, 'cerrar' => false]],
    // El moderador solo decide si el informe es válido, duplicado o no válido: no paga ni cierra.
    'validado' => ['validado', ['revisar' => false, 'validar' => false, 'rechazar' => true, 'pagar' => false, 'cerrar' => false]],
    'pagado' => ['pagado', ['validar' => false, 'rechazar' => false, 'pagar' => false, 'cerrar' => false]],
]);

test('la empresa duena del programa solo puede pagar y cerrar', function () {
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
        'pagar' => true,
        'cerrar' => true,
    ]);
});

test('el moderador no puede pagar ni cerrar informes', function () {
    $this->actingAs(moderador());
    $reporte = reporteDe(investigador(), null, ['estado' => 'validado']);

    $this->post(route('reportes.pagar', $reporte), ['recompensa' => 100])->assertForbidden();
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
    $user = moderador();
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

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
    $this->actingAs(moderador());
    $investigador = investigador();
    $reporte = reporteDe($investigador, null, ['estado' => 'enviado']);

    $this->post(route('reportes.validar', $reporte))->assertRedirect();

    expect($investigador->fresh()->reputation_score)->toBe((int) config('reputacion.puntos.reporte_validado'));
    $this->assertDatabaseHas('ledger_reputacion', [
        'usuario_id' => $investigador->id,
        'reporte_id' => $reporte->id,
        'motivo' => 'reporte_validado',
    ]);
});

test('pagar suma al investigador los puntos de reputación del evento reporte_pagado', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));
    $investigador = investigador();
    $reporte = reporteDe($investigador, $programa, ['estado' => 'validado']);

    $this->post(route('reportes.pagar', $reporte), ['recompensa' => 500])->assertRedirect();

    expect($investigador->fresh()->reputation_score)->toBe((int) config('reputacion.puntos.reporte_pagado'));
    $this->assertDatabaseHas('ledger_reputacion', [
        'usuario_id' => $investigador->id,
        'reporte_id' => $reporte->id,
        'motivo' => 'reporte_pagado',
    ]);
});

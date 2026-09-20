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

test('moderador can pay reporte with recompensa', function () {
    $user = moderador();
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), null, ['estado' => 'pago_pendiente', 'moneda' => 'USD']);

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

test('moderador can cerrar reporte', function () {
    $user = moderador();
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), null, ['estado' => 'pagado']);

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

    $response = $this->post(route('reportes.validar', $reporte));
    $response->assertUnprocessable();
});

test('admin and gestion cannot triaje reportes they cannot access', function (User $usuario) {
    $this->actingAs($usuario);

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

    $this->post(route('reportes.validar', $reporte))->assertForbidden();
    $this->post(route('reportes.comentar', $reporte), ['nota' => 'hola'])->assertForbidden();
    $this->assertDatabaseHas('reportes', ['id' => $reporte->id, 'estado' => 'enviado']);
})->with([
    'administrador' => fn () => administrador(),
    'gestion' => fn () => gestion(),
]);

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

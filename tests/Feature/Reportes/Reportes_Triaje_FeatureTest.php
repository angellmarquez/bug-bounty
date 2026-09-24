<?php

use App\Models\Empresa;
use App\Models\Programa;
use App\Models\User;

test('el administrador asigna un reporte enviado a un moderador del programa', function () {
    $programaModerado = Programa::factory()->create();
    $user = administrador();
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
    $user = administrador();
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

    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado', 'asignado_a' => $user->id]);

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

    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado', 'asignado_a' => $user->id]);

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

    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado', 'asignado_a' => $user->id]);

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
    $duplicado = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado', 'asignado_a' => $user->id]);

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

    // Su labor termina al validar: el ABAC ya no le deja tocar un informe validado.
    $this->post(route('reportes.validar', $reporte))->assertForbidden();
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
    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => $estado]);
    $this->actingAs(moderadorDe($reporte));

    $acciones = $this->get(route('reportes.show', $reporte))->inertiaProps()['accionesDisponibles'];

    foreach ($esperadas as $accion => $disponible) {
        expect($acciones[$accion])->toBe($disponible, "{$estado}: {$accion}");
    }
})->with([
    'enviado' => ['enviado', ['revisar' => true, 'pedir_info' => true, 'validar' => true, 'rechazar' => true, 'reparacion' => false, 'cerrar' => false]],
    'en_revision' => ['en_revision', ['revisar' => false, 'pedir_info' => true, 'validar' => true, 'rechazar' => true, 'reparacion' => false, 'cerrar' => false]],
    'needs_info' => ['needs_info', ['revisar' => true, 'pedir_info' => false, 'validar' => true, 'rechazar' => true, 'reparacion' => false, 'cerrar' => false]],
    // El moderador solo decide si el informe es válido, duplicado o no válido: tras validar ya no lo toca, ni repara ni cierra.
    'validado' => ['validado', ['revisar' => false, 'validar' => false, 'rechazar' => false, 'reparacion' => false, 'cerrar' => false]],
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

test('el administrador no cierra informes: eso es de la empresa/moderador', function () {
    $this->actingAs(administrador());
    $reporte = reporteDe(investigador(), null, ['estado' => 'validado']);

    $this->post(route('reportes.cerrar', $reporte))->assertForbidden();
    expect($reporte->fresh()->estado->value)->toBe('validado');
});

test('una empresa ajena no puede cerrar el informe', function () {
    $duena = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $duena->id]);
    $reporte = reporteDe(investigador(), $programa, ['estado' => 'validado']);
    $this->actingAs(miembroDeEmpresa(Empresa::factory()->aprobada()->create()));

    $this->post(route('reportes.cerrar', $reporte))->assertForbidden();
    expect($reporte->fresh()->estado->value)->toBe('validado');
});

test('un moderador no triaja informes de programas que no modera', function (User $usuario) {
    $this->actingAs($usuario);

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

    $this->post(route('reportes.validar', $reporte))->assertForbidden();
    $this->post(route('reportes.comentar', $reporte), ['nota' => 'hola'])->assertForbidden();
    $this->assertDatabaseHas('reportes', ['id' => $reporte->id, 'estado' => 'enviado']);
})->with([
    'moderador de otro programa' => fn () => moderador(),
]);

test('admin no triaja reportes: no participa en el dia a dia (solo audita)', function () {
    $this->actingAs(administrador());

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

    $this->post(route('reportes.validar', $reporte))->assertForbidden();
    $this->assertDatabaseHas('reportes', ['id' => $reporte->id, 'estado' => 'enviado']);

    // Pero sí puede ver el contenido para auditar o resolver una apelación.
    $this->get(route('reportes.show', $reporte))->assertOk();
});

test('empresa member can read but not triaje reportes of its programas', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));

    $reporte = reporteDe(investigador(), $programa, ['estado' => 'rechazado']);

    $this->get(route('reportes.show', $reporte))->assertOk();
    $this->post(route('reportes.validar', $reporte))->assertForbidden();
    $this->post(route('reportes.rechazar', $reporte), ['nota' => 'x'])->assertForbidden();
    $this->assertDatabaseHas('reportes', ['id' => $reporte->id, 'estado' => 'rechazado']);
});

test('show page passes triaje props for moderador', function () {
    $programaModerado = Programa::factory()->create();
    $user = moderadorDe($programaModerado);
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'enviado']);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();

    // Es el siguiente de la cola y nadie lo tomó: solo puede iniciar la revisión.
    $this->assertTrue($props['puedeTriar']);
    $this->assertTrue($props['accionesDisponibles']['revisar']);
    $this->assertFalse($props['accionesDisponibles']['asignar']);
    $this->assertFalse($props['accionesDisponibles']['validar']);
    $this->assertFalse($props['accionesDisponibles']['rechazar']);
    $this->assertStringContainsString('siguiente informe de la cola', (string) $props['avisoCola']);

    $this->post(route('reportes.revisar', $reporte))->assertRedirect();
    $props = $this->get(route('reportes.show', $reporte))->inertiaProps();

    expect($reporte->fresh()->asignado_a)->toBe($user->id);
    $this->assertTrue($props['puedeTriar']);
    $this->assertFalse($props['accionesDisponibles']['asignar']);
    $this->assertTrue($props['accionesDisponibles']['validar']);
    $this->assertTrue($props['accionesDisponibles']['rechazar']);
    $this->assertNull($props['avisoCola']);
});

test('el moderador no asigna informes ni abre o tría los que esperan turno o revisa otro', function () {
    $programa = Programa::factory()->create();
    $moderador = moderadorDe($programa);
    $otro = moderadorDe($programa);
    $this->actingAs($moderador);

    $siguiente = reporteDe(investigador(), $programa, ['estado' => 'enviado', 'enviado_en' => now()->subHour()]);
    $enEspera = reporteDe(investigador(), $programa, ['estado' => 'enviado', 'enviado_en' => now()]);
    $deOtro = reporteDe(investigador(), $programa, ['estado' => 'en_revision', 'asignado_a' => $otro->id, 'enviado_en' => now()->subDay()]);

    $this->post(route('reportes.asignar', $siguiente), ['asignado_a' => $moderador->id])->assertForbidden();

    // Del siguiente de la cola solo se puede iniciar la revisión: el resto exige haberlo tomado.
    $this->post(route('reportes.validar', $siguiente))->assertForbidden();
    $this->post(route('reportes.rechazar', $siguiente), ['nota' => 'x'])->assertForbidden();
    $this->post(route('reportes.pedir-info', $siguiente), ['nota' => 'x'])->assertForbidden();

    foreach ([$enEspera, $deOtro] as $reporte) {
        $this->get(route('reportes.show', $reporte))->assertForbidden();
        $this->post(route('reportes.revisar', $reporte))->assertForbidden();
        $this->post(route('reportes.validar', $reporte))->assertForbidden();
        $this->post(route('reportes.rechazar', $reporte), ['nota' => 'x'])->assertForbidden();
        $this->post(route('reportes.pedir-info', $reporte), ['nota' => 'x'])->assertForbidden();
    }

    expect($siguiente->fresh()->asignado_a)->toBeNull()
        ->and($enEspera->fresh()->estado->value)->toBe('enviado')
        ->and($deOtro->fresh()->estado->value)->toBe('en_revision')
        ->and($deOtro->fresh()->asignado_a)->toBe($otro->id);
});

test('dos moderadores no pueden tomar el mismo informe', function () {
    $programa = Programa::factory()->create();
    $primero = moderadorDe($programa);
    $segundo = moderadorDe($programa);
    $reporte = reporteDe(investigador(), $programa, ['estado' => 'enviado']);

    $this->actingAs($primero)->post(route('reportes.revisar', $reporte))->assertRedirect();
    $this->actingAs($segundo)->post(route('reportes.revisar', $reporte))->assertForbidden();

    expect($reporte->fresh()->asignado_a)->toBe($primero->id);
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

test('validar no da puntos: los da la empresa al confirmar el informe', function () {
    $propietario = propietarioDeEmpresa();
    $programa = programaDeEmpresa($propietario);
    $investigador = investigador();
    $reporte = reporteDe($investigador, $programa, ['estado' => 'enviado']);

    $this->actingAs(moderadorDe($reporte))->post(route('reportes.validar', $reporte))->assertRedirect();
    expect($investigador->fresh()->reputation_score)->toBe(0);

    $this->actingAs($propietario)->post(route('reportes.reparacion', $reporte))->assertRedirect();

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

    // Cerrar directamente desde "validado" es también la confirmación: suma ambos eventos.
    expect($investigador->fresh()->reputation_score)
        ->toBe((int) config('reputacion.puntos.reporte_validado') + (int) config('reputacion.puntos.reporte_resuelto'));
    $this->assertDatabaseHas('ledger_reputacion', [
        'usuario_id' => $investigador->id,
        'reporte_id' => $reporte->id,
        'motivo' => 'reporte_resuelto',
    ]);
});

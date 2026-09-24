<?php

use App\Enums\EstadoReporte;
use App\Models\Programa;
use App\Models\Reporte;

test('descifrado de PoC por un moderador genera registro inmutable en auditoria', function () {
    $moderador = moderador();
    $programa = Programa::factory()->create();
    $moderador->programasModerados()->attach($programa->id);

    $investigador = investigador();
    $reporte = reporteDe($investigador, $programa, [
        'estado' => EstadoReporte::EnRevision->value,
        'asignado_a' => $moderador->id,
    ]);

    $this->actingAs($moderador)
        ->get(route('reportes.show', $reporte))
        ->assertSuccessful();

    $this->assertDatabaseHas('auditorias', [
        'usuario_id' => $moderador->id,
        'accion' => 'reportes.poc_descifrado',
        'entidad_type' => $reporte->getMorphClass(),
        'entidad_id' => $reporte->id,
    ]);
});

test('triaje ciego: el moderador nunca ve la identidad del autor, sí su rango', function (string $estado) {
    $moderador = moderador();
    $programa = Programa::factory()->create();
    $moderador->programasModerados()->attach($programa->id);

    $investigador = investigador(['name' => 'Alice SecretHacker', 'reputation_score' => 250]);
    // En 'enviado' lo abre por ser el siguiente de la cola; después, porque lo tomó.
    $reporte = reporteDe($investigador, $programa, ['estado' => $estado, 'asignado_a' => $estado === EstadoReporte::Enviado->value ? null : $moderador->id]);

    $response = $this->actingAs($moderador)
        ->get(route('reportes.show', $reporte));

    $response->assertSuccessful();
    $props = $response->viewData('page')['props'];

    expect($props['reporte']['investigador'])->toBe(['id' => 0, 'name' => Reporte::AUTOR_ANONIMO, 'reputation_score' => 250])
        ->and($props['reporte']['investigador_id'])->toBe(0)
        ->and(json_encode($props))->not->toContain('Alice SecretHacker')
        ->and(json_encode($props))->not->toContain($investigador->email);
})->with([EstadoReporte::Enviado->value, EstadoReporte::EnRevision->value, EstadoReporte::Validado->value]);

test('el administrador sí ve al autor del informe', function () {
    $investigador = investigador(['name' => 'Alice SecretHacker']);
    $reporte = reporteDe($investigador, Programa::factory()->create(), ['estado' => EstadoReporte::EnRevision->value]);

    $props = $this->actingAs(administrador())->get(route('reportes.show', $reporte))->viewData('page')['props'];

    expect($props['reporte']['investigador']['name'])->toBe('Alice SecretHacker');
});

test('un descifrado fallido del moderador también queda en auditoría', function () {
    $moderador = moderador();
    $programa = Programa::factory()->create();
    $moderador->programasModerados()->attach($programa->id);
    $reporte = reporteDe(investigador(), $programa, ['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => $moderador->id]);
    $reporte->forceFill(['descripcion' => '-----BEGIN PGP MESSAGE-----
roto
-----END PGP MESSAGE-----'])->save();

    $this->actingAs($moderador)->get(route('reportes.show', $reporte))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('cifradoIndisponible', true));

    $this->assertDatabaseHas('auditorias', [
        'usuario_id' => $moderador->id,
        'accion' => 'reportes.descifrado_fallido',
        'entidad_id' => $reporte->id,
    ]);
});

test('marcar como duplicado falla con 422 si el reporte original es posterior al actual (prevención de robo)', function () {
    $moderador = moderador();
    $programa = Programa::factory()->create();
    $moderador->programasModerados()->attach($programa->id);

    // Reporte legítimo recibido antes (hace 2 días)
    $reporteLegitimo = reporteDe(investigador(), $programa, [
        'estado' => EstadoReporte::EnRevision->value,
        'created_at' => now()->subDays(2),
        'asignado_a' => $moderador->id,
    ]);

    // Reporte sospechoso recibido después (hace 1 hora)
    $reporteSospechoso = reporteDe(investigador(), $programa, [
        'estado' => EstadoReporte::EnRevision->value,
        'created_at' => now()->subHour(),
    ]);

    // Intentar marcar el reporte legítimo como "duplicado" del sospechoso (más reciente) debe ser rechazado
    $this->actingAs($moderador)
        ->post(route('reportes.marcar-duplicado', $reporteLegitimo), [
            'reporte_duplicado_id' => $reporteSospechoso->id,
            'nota' => 'Intento de desvío',
        ])
        ->assertStatus(422);
});

test('marcar como duplicado tiene éxito si el original es anterior en el tiempo', function () {
    $moderador = moderador();
    $programa = Programa::factory()->create();
    $moderador->programasModerados()->attach($programa->id);

    $original = reporteDe(investigador(), $programa, [
        'estado' => EstadoReporte::Validado->value,
        'created_at' => now()->subDays(3),
    ]);

    $duplicado = reporteDe(investigador(), $programa, [
        'estado' => EstadoReporte::EnRevision->value,
        'created_at' => now()->subDay(),
        'asignado_a' => $moderador->id,
    ]);

    $this->actingAs($moderador)
        ->post(route('reportes.marcar-duplicado', $duplicado), [
            'reporte_duplicado_id' => $original->id,
            'nota' => 'Vulnerabilidad idéntica reportada antes',
        ])
        ->assertRedirect();

    expect($duplicado->fresh()->estado)->toBe(EstadoReporte::Duplicado);
});

test('empresa invita investigador a programa privado y este acepta la invitacion', function () {
    $propietario = propietarioDeEmpresa();
    $programa = programaDeEmpresa($propietario, [
        'es_publico' => false,
    ]);

    $hacker = investigador(['email' => 'elitehacker@example.com']);

    // La empresa lo invita
    $this->actingAs($propietario)
        ->post(route('programas.invitaciones.crear', $programa), [
            'email' => 'elitehacker@example.com',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('programa_invitados', [
        'programa_id' => $programa->id,
        'investigador_id' => $hacker->id,
        'estado' => 'pendiente',
    ]);

    // El hacker ve la invitación y la acepta
    $this->actingAs($hacker)
        ->post(route('invitaciones.programas.aceptar', $programa))
        ->assertRedirect();

    $this->assertDatabaseHas('programa_invitados', [
        'programa_id' => $programa->id,
        'investigador_id' => $hacker->id,
        'estado' => 'aceptada',
    ]);

    // Ahora el hacker puede ver el programa
    expect(auth()->user()->programasInvitados()->where('programa_id', $programa->id)->wherePivot('estado', 'aceptada')->exists())->toBeTrue();
});

test('una invitacion rechazada no se puede aceptar despues para colarse en el programa', function () {
    $propietario = propietarioDeEmpresa();
    $programa = programaDeEmpresa($propietario, ['es_publico' => false]);
    $hacker = investigador();
    $programa->hackersInvitados()->attach($hacker->id, ['invitado_por' => $propietario->id, 'estado' => 'rechazada']);

    $this->actingAs($hacker)->post(route('invitaciones.programas.aceptar', $programa))->assertNotFound();

    $this->assertDatabaseHas('programa_invitados', ['investigador_id' => $hacker->id, 'estado' => 'rechazada']);
});

test('el moderador pide informacion y el investigador responde reenviando el informe', function () {
    $programa = Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]);
    $moderador = moderadorDe($programa);
    $autor = investigador();
    $reporte = reporteDe($autor, $programa, [
        'estado' => EstadoReporte::EnRevision->value,
        'poc' => pocCifrado(['evidencia' => 'Pasos iniciales.']),
        'asignado_a' => $moderador->id,
    ]);

    $this->actingAs($moderador)
        ->post(route('reportes.pedir-info', $reporte), ['nota' => 'Adjunta la petición HTTP completa.'])
        ->assertRedirect();
    expect($reporte->fresh()->estado->value)->toBe('needs_info');

    // El investigador edita y lo reenvía; vuelve a la cola del moderador.
    $this->actingAs($autor)
        ->put(route('reportes.update', $reporte), ['titulo' => $reporte->titulo, 'descripcion' => 'Con la petición completa.', 'poc' => ['evidencia' => 'GET /api?id=1 HTTP/1.1']])
        ->assertRedirect();
    $this->post(route('reportes.enviar', $reporte))->assertRedirect()->assertSessionMissing('error');

    expect($reporte->fresh()->estado->value)->toBe('enviado');
});

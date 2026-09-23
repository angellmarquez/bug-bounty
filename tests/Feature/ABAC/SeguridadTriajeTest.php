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

test('triaje ciego en estado enviado anonimiza el autor e historial ante el moderador', function () {
    $moderador = moderador();
    $programa = Programa::factory()->create();
    $moderador->programasModerados()->attach($programa->id);

    $investigador = investigador(['name' => 'Alice SecretHacker', 'reputation_score' => 250]);
    $reporte = reporteDe($investigador, $programa, [
        'estado' => EstadoReporte::Enviado->value,
    ]);

    $response = $this->actingAs($moderador)
        ->get(route('reportes.show', $reporte));

    $response->assertSuccessful();
    $page = $response->viewData('page');
    $props = $page['props'];

    expect($props['reporte']['investigador']['name'])->toBe('Investigador Anónimo (Triaje Ciego)')
        ->and($props['reporte']['investigador']['reputation_score'])->toBeNull();
});

test('triaje desanonimiza autor cuando el reporte avanza a en revision', function () {
    $moderador = moderador();
    $programa = Programa::factory()->create();
    $moderador->programasModerados()->attach($programa->id);

    $investigador = investigador(['name' => 'Alice SecretHacker', 'reputation_score' => 250]);
    $reporte = reporteDe($investigador, $programa, [
        'estado' => EstadoReporte::EnRevision->value,
    ]);

    $response = $this->actingAs($moderador)
        ->get(route('reportes.show', $reporte));

    $response->assertSuccessful();
    $page = $response->viewData('page');
    $props = $page['props'];

    expect($props['reporte']['investigador']['name'])->toBe('Alice SecretHacker')
        ->and($props['reporte']['investigador']['reputation_score'])->toBe(250);
});

test('marcar como duplicado falla con 422 si el reporte original es posterior al actual (prevención de robo)', function () {
    $moderador = moderador();
    $programa = Programa::factory()->create();
    $moderador->programasModerados()->attach($programa->id);

    // Reporte legítimo recibido antes (hace 2 días)
    $reporteLegitimo = reporteDe(investigador(), $programa, [
        'estado' => EstadoReporte::EnRevision->value,
        'created_at' => now()->subDays(2),
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

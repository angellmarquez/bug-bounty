<?php

use App\Models\Programa;
use Illuminate\Support\Facades\DB;

test('la empresa puede crear un programa privado con es_publico en 0', function () {
    $propietario = propietarioDeEmpresa();

    $response = $this->actingAs($propietario)->post(route('programas.store'), [
        'nombre' => 'Programa Secreto Alpha',
        'descripcion' => 'Alcance privado exclusivo.',
        'es_publico' => 0,
        'nivel_acceso' => 'bajo',
        'objetivos' => [
            ['tipo' => 'web', 'valor' => 'https://alpha.internal.priv', 'descripcion' => 'Web interna'],
        ],
    ]);

    $programa = Programa::where('nombre', 'Programa Secreto Alpha')->first();
    expect($programa)->not->toBeNull();
    expect($programa->es_publico)->toBeFalse();
});

test('la empresa puede editar un programa para cambiar su visibilidad a privada o publica', function () {
    $propietario = propietarioDeEmpresa();
    $programa = programaDeEmpresa($propietario, ['es_publico' => true]);

    $this->actingAs($propietario)->put(route('programas.update', $programa), [
        'nombre' => $programa->nombre,
        'descripcion' => 'Nueva descripcion',
        'es_publico' => 0,
        'nivel_acceso' => 'bajo',
    ]);

    expect($programa->fresh()->es_publico)->toBeFalse();

    $this->actingAs($propietario)->put(route('programas.update', $programa), [
        'nombre' => $programa->nombre,
        'descripcion' => 'Nueva descripcion',
        'es_publico' => 1,
        'nivel_acceso' => 'bajo',
    ]);

    expect($programa->fresh()->es_publico)->toBeTrue();
});

test('un investigador no invitado no ve el programa privado en el catalogo', function () {
    $propietario = propietarioDeEmpresa();
    $privado = programaDeEmpresa($propietario, [
        'nombre' => 'Top Secret Target',
        'estado' => 'activo',
        'es_publico' => false,
    ]);
    $hacker = investigador();

    $response = $this->actingAs($hacker)->get(route('programas.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('programas.data', fn ($data) => collect($data)->every(fn ($p) => $p['id'] !== $privado->id)));
});

test('flujo completo: invitar hacker, notificarlo, aceptar en /invitaciones y obtener acceso', function () {
    $propietario = propietarioDeEmpresa();
    $programa = programaDeEmpresa($propietario, [
        'nombre' => 'Programa Exclusivo Fintech',
        'estado' => 'activo',
        'es_publico' => false,
    ]);
    $hacker = investigador(['email' => 'hacker.vip@test.local']);

    // 1. Antes de la invitación, el hacker no puede ver el programa
    $this->actingAs($hacker)->get(route('programas.show', $programa))
        ->assertForbidden();

    // 2. La empresa invita al hacker por su email
    $this->actingAs($propietario)->post(route('programas.invitaciones.crear', $programa), [
        'email' => 'hacker.vip@test.local',
    ])->assertRedirect();

    // Comprobar registro en programa_invitados
    expect(DB::table('programa_invitados')
        ->where('programa_id', $programa->id)
        ->where('investigador_id', $hacker->id)
        ->where('estado', 'pendiente')
        ->exists())->toBeTrue();

    // Comprobar que el hacker recibió la notificación en BD
    $notificacion = $hacker->notifications()->first();
    expect($notificacion)->not->toBeNull();
    expect($notificacion->data['tipo'])->toBe('invitacion');
    expect($notificacion->data['url'])->toBe('/invitaciones');

    // 3. El hacker consulta /invitaciones y ve la invitación pendiente
    $this->actingAs($hacker)->get(route('invitaciones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('invitacionesProgramas', 1)
            ->where('invitacionesProgramas.0.programa_id', $programa->id)
            ->where('invitacionesProgramas.0.estado', 'pendiente'));

    // 4. El hacker acepta la invitación
    $this->actingAs($hacker)->post(route('invitaciones.programas.aceptar', $programa))
        ->assertRedirect(route('programas.show', $programa));

    expect(DB::table('programa_invitados')
        ->where('programa_id', $programa->id)
        ->where('investigador_id', $hacker->id)
        ->where('estado', 'aceptada')
        ->exists())->toBeTrue();

    // La empresa recibió notificación de aceptación
    $notificacionEmpresa = $propietario->notifications()->first();
    expect($notificacionEmpresa)->not->toBeNull();
    expect($notificacionEmpresa->data['tipo'])->toBe('invitacion');

    // 5. Ahora el hacker puede ver el programa privado
    $this->actingAs($hacker)->get(route('programas.show', $programa))
        ->assertOk();

    // 6. La empresa puede cancelar/retirar la invitación
    $this->actingAs($propietario)->delete(route('programas.invitaciones.cancelar', [$programa, $hacker]))
        ->assertRedirect();

    // El hacker ya no tiene la invitación y pierde acceso
    expect(DB::table('programa_invitados')
        ->where('programa_id', $programa->id)
        ->where('investigador_id', $hacker->id)
        ->exists())->toBeFalse();

    $this->actingAs($hacker)->get(route('programas.show', $programa))
        ->assertForbidden();
});

test('el hacker puede rechazar una invitacion a un programa privado', function () {
    $propietario = propietarioDeEmpresa();
    $programa = programaDeEmpresa($propietario, [
        'nombre' => 'Programa Rehusado',
        'estado' => 'activo',
        'es_publico' => false,
    ]);
    $hacker = investigador(['email' => 'hacker.declined@test.local']);

    $this->actingAs($propietario)->post(route('programas.invitaciones.crear', $programa), [
        'email' => 'hacker.declined@test.local',
    ]);

    $this->actingAs($hacker)->post(route('invitaciones.programas.rechazar', $programa))
        ->assertRedirect(route('invitaciones.index'));

    expect(DB::table('programa_invitados')
        ->where('programa_id', $programa->id)
        ->where('investigador_id', $hacker->id)
        ->where('estado', 'rechazada')
        ->exists())->toBeTrue();

    // No puede ver el programa
    $this->actingAs($hacker)->get(route('programas.show', $programa))
        ->assertForbidden();
});

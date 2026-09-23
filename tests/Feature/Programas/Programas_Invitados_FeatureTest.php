<?php

test('la empresa ve en el programa a sus investigadores invitados con el estado de cada invitacion', function () {
    $propietario = propietarioDeEmpresa();
    $programa = programaDeEmpresa($propietario, ['es_publico' => false]);
    $aceptado = investigador()->fresh();
    $pendiente = investigador()->fresh();
    $programa->hackersInvitados()->attach($aceptado->id, ['invitado_por' => $propietario->id, 'estado' => 'aceptada']);
    $programa->hackersInvitados()->attach($pendiente->id, ['invitado_por' => $propietario->id, 'estado' => 'pendiente']);

    $this->actingAs($propietario)->get(route('programas.show', $programa))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('hackersInvitados', 2)
            ->where('hackersInvitados', fn ($invitados) => collect($invitados)->sortBy('id')->values()->all() === [
                [
                    'id' => $aceptado->id,
                    'name' => $aceptado->name,
                    'email' => $aceptado->email,
                    'reputation_score' => $aceptado->reputation_score,
                    'estado' => 'aceptada',
                ],
                [
                    'id' => $pendiente->id,
                    'name' => $pendiente->name,
                    'email' => $pendiente->email,
                    'reputation_score' => $pendiente->reputation_score,
                    'estado' => 'pendiente',
                ],
            ]));
});

test('un investigador no recibe la lista de invitados del programa', function () {
    $propietario = propietarioDeEmpresa();
    $programa = programaDeEmpresa($propietario, ['es_publico' => false]);
    $hacker = investigador();
    $programa->hackersInvitados()->attach($hacker->id, ['invitado_por' => $propietario->id, 'estado' => 'aceptada']);

    $this->actingAs($hacker)->get(route('programas.show', $programa))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hackersInvitados', []));
});

<?php

use App\Models\Empresa;
use App\Models\Reporte;

test('la empresa puede asignar un monto de bounty a un reporte validado', function () {
    $empresaUser = propietarioDeEmpresa();
    $programa = programaDeEmpresa($empresaUser, ['tiene_recompensas' => true, 'moneda' => 'USDC']);
    $investigador = investigador();
    $reporte = reporteDe($investigador, $programa, ['estado' => 'validado']);

    $this->actingAs($empresaUser)
        ->post(route('reportes.asignar-bounty', $reporte), [
            'monto' => 500,
            'moneda' => 'USDC',
        ])
        ->assertRedirect();

    $reporte->refresh();
    expect((float) $reporte->bounty_monto)->toEqual(500.0)
        ->and($reporte->bounty_moneda)->toBe('USDC')
        ->and($reporte->bounty_estado)->toBe('asignado');
});

test('un investigador no puede asignarse bounty a si mismo', function () {
    $empresaUser = propietarioDeEmpresa();
    $programa = programaDeEmpresa($empresaUser, ['tiene_recompensas' => true]);
    $investigador = investigador();
    $reporte = reporteDe($investigador, $programa, ['estado' => 'validado']);

    $this->actingAs($investigador)
        ->post(route('reportes.asignar-bounty', $reporte), [
            'monto' => 1000,
            'moneda' => 'USDC',
        ])
        ->assertForbidden();
});

test('la empresa puede registrar el pago de un bounty con un tx_hash valido', function () {
    $empresaUser = propietarioDeEmpresa();
    $programa = programaDeEmpresa($empresaUser, ['tiene_recompensas' => true]);
    $investigador = investigador([
        'wallet_address' => '0x71C83638372331E200C025434d31B057C9a63974',
        'wallet_red' => 'polygon',
    ]);
    $reporte = reporteDe($investigador, $programa, [
        'estado' => 'validado',
        'bounty_monto' => 350,
        'bounty_estado' => 'asignado',
    ]);

    $txHashValido = '0x'.str_repeat('a', 64);

    $this->actingAs($empresaUser)
        ->post(route('reportes.pagar-bounty', $reporte), [
            'tx_hash' => $txHashValido,
            'red' => 'polygon',
        ])
        ->assertRedirect();

    $reporte->refresh();
    expect($reporte->bounty_estado)->toBe('pagado')
        ->and($reporte->bounty_tx_hash)->toBe(strtolower($txHashValido))
        ->and($reporte->bounty_red)->toBe('polygon')
        ->and($reporte->bounty_pagado_en)->not->toBeNull();
});

test('se rechaza un tx_hash con formato invalido al registrar pago', function () {
    $empresaUser = propietarioDeEmpresa();
    $programa = programaDeEmpresa($empresaUser, ['tiene_recompensas' => true]);
    $investigador = investigador();
    $reporte = reporteDe($investigador, $programa, [
        'estado' => 'validado',
        'bounty_monto' => 350,
        'bounty_estado' => 'asignado',
    ]);

    $this->actingAs($empresaUser)
        ->post(route('reportes.pagar-bounty', $reporte), [
            'tx_hash' => 'hash_invalido_sin_formato_evm',
            'red' => 'polygon',
        ])
        ->assertSessionHasErrors(['tx_hash']);

    $reporte->refresh();
    expect($reporte->bounty_estado)->toBe('asignado');
});

test('se impide el reuso de un tx_hash ya utilizado (anti-replay)', function () {
    $empresaUser = propietarioDeEmpresa();
    $programa = programaDeEmpresa($empresaUser, ['tiene_recompensas' => true]);
    $investigador = investigador();

    $txHash = '0x'.str_repeat('b', 64);

    $reporte1 = reporteDe($investigador, $programa, [
        'estado' => 'validado',
        'bounty_monto' => 200,
        'bounty_estado' => 'asignado',
    ]);

    // Pagamos el primer reporte
    $this->actingAs($empresaUser)
        ->post(route('reportes.pagar-bounty', $reporte1), [
            'tx_hash' => $txHash,
            'red' => 'polygon',
        ])
        ->assertRedirect();

    $reporte2 = reporteDe($investigador, $programa, [
        'estado' => 'validado',
        'bounty_monto' => 300,
        'bounty_estado' => 'asignado',
    ]);

    // Intentamos reutilizar el mismo tx_hash para el segundo reporte
    $this->actingAs($empresaUser)
        ->post(route('reportes.pagar-bounty', $reporte2), [
            'tx_hash' => $txHash,
            'red' => 'polygon',
        ])
        ->assertSessionHasErrors(['tx_hash']);
});

test('empresa con plan comunitario no puede crear programas con nivel de acceso elite', function () {
    $empresaUser = propietarioDeEmpresa();
    /** @var Empresa $empresa */
    $empresa = $empresaUser->empresas()->first();
    $empresa->update(['plan' => 'comunitario']);

    $this->actingAs($empresaUser)
        ->post(route('programas.store'), [
            'nombre' => 'Programa Elite Intentado',
            'descripcion' => 'Intento de crear programa con nivel alto con plan comunitario',
            'nivel_acceso' => 'alto',
            'es_publico' => true,
            'objetivos' => [
                ['tipo' => 'web', 'valor' => 'https://ejemplo.com', 'descripcion' => 'Web principal'],
            ],
        ])
        ->assertSessionHasErrors(['nivel_acceso']);
});

test('empresa con plan comunitario no puede crear programas privados', function () {
    $empresaUser = propietarioDeEmpresa();
    /** @var Empresa $empresa */
    $empresa = $empresaUser->empresas()->first();
    $empresa->update(['plan' => 'comunitario']);

    $this->actingAs($empresaUser)
        ->post(route('programas.store'), [
            'nombre' => 'Programa Privado Intentado',
            'descripcion' => 'Intento de crear programa privado con plan comunitario',
            'nivel_acceso' => 'bajo',
            'es_publico' => false,
            'objetivos' => [
                ['tipo' => 'web', 'valor' => 'https://ejemplo.com', 'descripcion' => 'Web principal'],
            ],
        ])
        ->assertSessionHasErrors(['es_publico']);
});

test('empresa con plan profesional puede crear programas privados y con nivel elite', function () {
    $empresaUser = propietarioDeEmpresa();
    /** @var Empresa $empresa */
    $empresa = $empresaUser->empresas()->first();
    $empresa->update(['plan' => 'profesional']);

    $this->actingAs($empresaUser)
        ->post(route('programas.store'), [
            'nombre' => 'Programa Elite Privado',
            'descripcion' => 'Programa privado exclusivo para investigadores con rango alto',
            'nivel_acceso' => 'alto',
            'es_publico' => false,
            'tiene_recompensas' => true,
            'moneda' => 'USDC',
            'recompensa_min' => 100,
            'recompensa_max' => 2000,
            'tabla_recompensas' => [
                'critica' => 1500,
                'alta' => 800,
                'media' => 300,
                'baja' => 100,
            ],
            'objetivos' => [
                ['tipo' => 'web', 'valor' => 'https://seguro.ejemplo.com', 'descripcion' => 'Portal interno'],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('programas', [
        'nombre' => 'Programa Elite Privado',
        'es_publico' => false,
        'nivel_acceso' => 'alto',
        'tiene_recompensas' => true,
        'recompensa_max' => 2000,
    ]);
});

test('investigador puede actualizar su wallet evm en su perfil', function () {
    $investigador = investigador();
    $walletValida = '0x'.str_repeat('c', 40);

    $this->actingAs($investigador)
        ->patch(route('profile.update'), [
            'name' => $investigador->name,
            'email' => $investigador->email,
            'wallet_address' => $walletValida,
            'wallet_red' => 'polygon',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    $investigador->refresh();
    expect($investigador->wallet_address)->toBe($walletValida)
        ->and($investigador->wallet_red)->toBe('polygon');
});

test('se rechaza una direccion de wallet no valida en el perfil', function () {
    $investigador = investigador();

    $this->actingAs($investigador)
        ->patch(route('profile.update'), [
            'name' => $investigador->name,
            'email' => $investigador->email,
            'wallet_address' => 'direccion_invalida_sin_0x',
            'wallet_red' => 'polygon',
        ])
        ->assertSessionHasErrors(['wallet_address']);
});

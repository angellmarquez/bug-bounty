<?php

use App\Models\Empresa;
use App\Models\Programa;

// Los pagos (asignar, registrar y verificar en la blockchain) están en BountyPagos_FeatureTest.

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
    $investigador = investigador(['name' => 'Investigador Valido']);
    $walletValida = '0x'.str_repeat('c', 40);

    $this->actingAs($investigador)
        ->patch(route('profile.update'), [
            'name' => $investigador->name,
            'email' => $investigador->email,
            'wallet_address' => $walletValida,
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    $investigador->refresh();
    expect($investigador->wallet_address)->toBe($walletValida);
});

test('se rechaza una direccion de wallet no valida en el perfil', function () {
    $investigador = investigador(['name' => 'Investigador Valido']);

    $this->actingAs($investigador)
        ->patch(route('profile.update'), [
            'name' => $investigador->name,
            'email' => $investigador->email,
            'wallet_address' => 'direccion_invalida_sin_0x',
        ])
        ->assertSessionHasErrors(['wallet_address']);
});

test('investigador no verificado no puede crear reporte en programa solo para verificados', function () {
    $empresa = Empresa::factory()->aprobada()->create(['plan' => 'profesional']);
    $empresaUser = miembroDeEmpresa($empresa);
    $programa = conObjetivo(Programa::factory()->create([
        'empresa_id' => $empresa->id,
        'creado_por' => $empresaUser->id,
        'solo_verificados' => true,
        'es_publico' => true,
        'estado' => 'activo',
    ]));

    $investigador = investigador(); // 0 reportes validados
    expect($investigador->esVerificado())->toBeFalse();

    $this->actingAs($investigador)
        ->post(route('reportes.store'), [
            'programa_id' => $programa->id,
            'titulo' => 'Vulnerabilidad SQL Injection',
            'descripcion' => 'Descripción detallada',
        ])
        ->assertForbidden();
});

test('investigador con 3 o mas reportes validados es verificado y puede crear reporte en programa solo_verificados', function () {
    $empresa = Empresa::factory()->aprobada()->create(['plan' => 'profesional']);
    $empresaUser = miembroDeEmpresa($empresa);
    $programa = conObjetivo(Programa::factory()->create([
        'empresa_id' => $empresa->id,
        'creado_por' => $empresaUser->id,
        'solo_verificados' => true,
        'es_publico' => true,
        'estado' => 'activo',
    ]));

    $investigador = investigador();

    // Crear 3 reportes validados
    reporteDe($investigador, null, ['estado' => 'en_reparacion']);
    reporteDe($investigador, null, ['estado' => 'en_reparacion']);
    reporteDe($investigador, null, ['estado' => 'cerrado']);

    expect($investigador->cantidadReportesValidados())->toBe(3)
        ->and($investigador->esVerificado())->toBeTrue();

    $this->actingAs($investigador)
        ->post(route('reportes.store'), [
            'programa_id' => $programa->id,
            'titulo' => 'Reporte legítimo de investigador verificado',
            'descripcion' => 'PoC bien documentado',
        ])
        ->assertRedirect();
});

test('empresa con plan comunitario no puede crear programa exclusivo para verificados', function () {
    $empresa = Empresa::factory()->aprobada()->create(['plan' => 'comunitario']);
    $empresaUser = miembroDeEmpresa($empresa);

    $this->actingAs($empresaUser)
        ->post(route('programas.store'), [
            'nombre' => 'Programa Verificado Ilegal',
            'descripcion' => 'Intento en plan comunitario',
            'objetivos' => [['tipo' => 'web', 'valor' => 'app.com']],
            'solo_verificados' => true,
        ])
        ->assertSessionHasErrors(['solo_verificados']);
});

test('empresa con plan profesional si puede crear programa exclusivo para verificados', function () {
    $empresa = Empresa::factory()->aprobada()->create(['plan' => 'profesional']);
    $empresaUser = miembroDeEmpresa($empresa);

    $this->actingAs($empresaUser)
        ->post(route('programas.store'), [
            'nombre' => 'Programa Verificado Legal',
            'descripcion' => 'Creado con plan profesional',
            'objetivos' => [['tipo' => 'web', 'valor' => 'app.com']],
            'solo_verificados' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('programas', [
        'nombre' => 'Programa Verificado Legal',
        'solo_verificados' => true,
    ]);
});

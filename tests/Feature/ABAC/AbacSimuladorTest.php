<?php

use App\Enums\GravedadSancion;
use App\Models\Programa;
use App\Services\Reputacion\ReputationService;

test('solo administradores pueden acceder a la interfaz del simulador ABAC', function () {
    $this->actingAs(investigador())
        ->get(route('admin.abac.simulador'))
        ->assertForbidden();

    $this->actingAs(moderador())
        ->get(route('admin.abac.simulador'))
        ->assertForbidden();

    $this->actingAs(propietarioDeEmpresa())
        ->get(route('admin.abac.simulador'))
        ->assertForbidden();

    $this->actingAs(administrador())
        ->get(route('admin.abac.simulador'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/abac/Simulador')
            ->has('usuarios')
            ->has('acciones')
            ->has('recursos'));
});

test('el simulador evalua acciones y devuelve el desglose de reglas y contexto', function () {
    $admin = administrador();
    $investigador = investigador();
    $programa = Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]);

    // Caso 1: Investigador creando reporte en programa activo -> Permitido
    $response = $this->actingAs($admin)->postJson(route('admin.abac.simular'), [
        'usuario_id' => $investigador->id,
        'accion' => 'reportes.crear',
        'tipo_recurso' => 'programa',
        'recurso_id' => $programa->id,
    ]);

    $response->assertOk()
        ->assertJson([
            'permitido' => true,
            'decision' => 'permitir',
        ])
        ->assertJsonStructure([
            'permitido',
            'decision',
            'motivo',
            'contexto' => ['sujeto', 'objeto', 'entorno'],
            'detalle',
        ]);

    // Caso 2: Investigador suspendido creando reporte -> Denegado
    app(ReputationService::class)->aplicarSancion($investigador, 'violacion_normas', GravedadSancion::Grave);

    $responseSancionado = $this->actingAs($admin)->postJson(route('admin.abac.simular'), [
        'usuario_id' => $investigador->id,
        'accion' => 'reportes.crear',
        'tipo_recurso' => 'programa',
        'recurso_id' => $programa->id,
    ]);

    $responseSancionado->assertOk()
        ->assertJson([
            'permitido' => false,
            'decision' => 'denegar',
        ]);

    // Caso 3: Moderador intentando resolver apelación -> Denegado
    $mod = moderador();
    $responseModApelacion = $this->actingAs($admin)->postJson(route('admin.abac.simular'), [
        'usuario_id' => $mod->id,
        'accion' => 'apelaciones.resolver',
        'tipo_recurso' => 'ninguno',
    ]);

    $responseModApelacion->assertOk()
        ->assertJson([
            'permitido' => false,
            'decision' => 'denegar',
        ]);
});

test('el simulador usa el mismo entorno que la app: la empresa ve su propio programa en borrador', function () {
    $duena = propietarioDeEmpresa();
    $borrador = programaDeEmpresa($duena, ['estado' => 'borrador']);

    // En la aplicación real, la empresa abre su programa en borrador…
    $this->actingAs($duena)->get(route('programas.show', $borrador))->assertOk();

    // …y el simulador tiene que decir lo mismo.
    $this->actingAs(administrador())->postJson(route('admin.abac.simular'), [
        'usuario_id' => $duena->id,
        'accion' => 'programas.ver',
        'tipo_recurso' => 'programa',
        'recurso_id' => $borrador->id,
    ])->assertOk()
        ->assertJson(['permitido' => true, 'regla_decisiva' => 'empresa-ver-programa-propio'])
        ->assertJsonPath('contexto.entorno.empresa_id', $borrador->empresa_id);

    // Otra empresa no.
    $this->actingAs(administrador())->postJson(route('admin.abac.simular'), [
        'usuario_id' => propietarioDeEmpresa()->id,
        'accion' => 'programas.ver',
        'tipo_recurso' => 'programa',
        'recurso_id' => $borrador->id,
    ])->assertJson(['permitido' => false]);
});

test('todas las reglas tienen una descripcion en lenguaje llano', function () {
    foreach (config('abac.reglas') as $regla) {
        expect($regla['descripcion'] ?? '')->not->toBe('', "La regla {$regla['id']} no tiene descripción.");
    }
});

test('explica en lenguaje llano por que se deniega gestionar un programa sin indicar cual', function () {
    $duena = propietarioDeEmpresa();
    $propio = programaDeEmpresa($duena);

    $sinPrograma = $this->actingAs(administrador())->postJson(route('admin.abac.simular'), [
        'usuario_id' => $duena->id,
        'accion' => 'programas.gestionar',
        'tipo_recurso' => 'ninguno',
    ])->assertOk()->json();

    expect($sinPrograma['permitido'])->toBeFalse()
        ->and($sinPrograma['explicacion']['paso'])->toBe('por_defecto')
        ->and($sinPrograma['explicacion']['resumen'])->toContain('NO puede')->toContain('deniega por defecto')
        // Lo que faltó: que el programa sea de su empresa.
        ->and($sinPrograma['explicacion']['casi']['regla'])->toBe('empresa-gestionar-programa-propio')
        ->and(collect($sinPrograma['explicacion']['casi']['faltan'])->pluck('grupo')->all())->toContain('Sobre qué');

    $conPrograma = $this->actingAs(administrador())->postJson(route('admin.abac.simular'), [
        'usuario_id' => $duena->id,
        'accion' => 'programas.gestionar',
        'tipo_recurso' => 'programa',
        'recurso_id' => $propio->id,
    ])->assertOk()->json();

    expect($conPrograma['permitido'])->toBeTrue()
        ->and($conPrograma['explicacion']['paso'])->toBe('permiso')
        ->and($conPrograma['explicacion']['resumen'])->toContain('SÍ puede')->toContain($propio->nombre);
});

test('una regla que deniega se explica como la que decide y muestra cada condicion con su valor real', function () {
    $programa = Programa::factory()->create();
    $moderador = moderadorDe($programa);
    $autor = investigador();
    $reporte = reporteDe($autor, $programa, ['estado' => 'en_revision', 'asignado_a' => $moderador->id]);

    $respuesta = $this->actingAs(administrador())->postJson(route('admin.abac.simular'), [
        'usuario_id' => $autor->id,
        'accion' => 'reportes.validar',
        'tipo_recurso' => 'reporte',
        'recurso_id' => $reporte->id,
    ])->assertOk()->json();

    expect($respuesta['explicacion']['paso'])->toBe('denegacion');
    $triaje = collect($respuesta['explicacion']['reglas'])->firstWhere('regla', 'moderador-triaje-asignado');
    $asignado = collect($triaje['condiciones'])->first(fn ($c) => str_contains($c['texto'], 'moderador asignado'));
    // El valor real se muestra con el nombre de la persona, no con su id.
    expect($asignado['cumple'])->toBeFalse()->and($asignado['actual'])->toBe($moderador->name);
});

test('los casos de ejemplo anuncian lo mismo que responde el motor', function () {
    $duena = propietarioDeEmpresa();
    programaDeEmpresa($duena);
    Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]);
    investigador();

    $casos = $this->actingAs(administrador())->get(route('admin.abac.simulador'))
        ->assertOk()
        ->viewData('page')['props']['casos'];

    expect($casos)->not->toBeEmpty();
    foreach ($casos as $caso) {
        $this->actingAs(administrador())->postJson(route('admin.abac.simular'), $caso)
            ->assertJsonPath('permitido', $caso['permitido']);
    }
});

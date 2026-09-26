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

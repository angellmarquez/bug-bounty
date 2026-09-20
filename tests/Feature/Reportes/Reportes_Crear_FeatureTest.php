<?php

use App\Models\Programa;
use App\Models\Reporte;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('reportes.create'));
    $response->assertRedirect(route('login'));
});

test('authenticated user can visit create page', function () {
    $user = investigador();
    $this->actingAs($user);

    $response = $this->get(route('reportes.create'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('reportes/Create'));
});

test('create page returns active public programs', function () {
    $user = investigador();
    $this->actingAs($user);

    Programa::factory()->create(['nombre' => 'Activo', 'estado' => 'activo', 'es_publico' => true]);
    Programa::factory()->create(['nombre' => 'Inactivo', 'estado' => 'borrador']);

    $response = $this->get(route('reportes.create'));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertCount(1, $props['programas']);
    $this->assertEquals('Activo', $props['programas'][0]['nombre']);
});

test('investigador creates borrador successfully', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $response = $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'XSS en login',
        'descripcion' => 'Vulnerabilidad XSS encontrada en el campo de usuario',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'investigador_id' => $user->id,
        'programa_id' => $programa->id,
        'titulo' => 'XSS en login',
        'estado' => 'borrador',
    ]);
});

test('numero_reporte is generated uniquely', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Reporte 1',
        'descripcion' => 'Desc 1',
    ]);

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Reporte 2',
        'descripcion' => 'Desc 2',
    ]);

    $reportes = Reporte::where('investigador_id', $user->id)->get();
    $this->assertCount(2, $reportes);
    $this->assertNotEquals(
        $reportes[0]->numero_reporte,
        $reportes[1]->numero_reporte
    );
    $this->assertMatchesRegularExpression('/^BB-\d{4}-\d{4}$/', $reportes[0]->numero_reporte);
});

test('creado event is created on store', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test desc',
    ]);

    $reporte = Reporte::where('investigador_id', $user->id)->first();
    $this->assertNotNull($reporte);
    $this->assertDatabaseHas('eventos_reporte', [
        'reporte_id' => $reporte->id,
        'tipo' => 'creado',
    ]);
});

test('titulo is required', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $response = $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => '',
        'descripcion' => 'Test',
    ]);

    $response->assertSessionHasErrors('titulo');
});

test('descripcion is required', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $response = $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => '',
    ]);

    $response->assertSessionHasErrors('descripcion');
});

test('programa_id is required', function () {
    $user = investigador();
    $this->actingAs($user);

    $response = $this->post(route('reportes.store'), [
        'titulo' => 'Test',
        'descripcion' => 'Test',
    ]);

    $response->assertSessionHasErrors('programa_id');
});

test('poc is saved as array', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test PoC',
        'descripcion' => 'Test desc',
        'poc' => ['pasos' => 'curl -k https://...', 'evidencia' => 'xss payload'],
    ]);

    $reporte = Reporte::where('investigador_id', $user->id)->first();
    $this->assertNotNull($reporte->poc);
    $this->assertEquals('curl -k https://...', pocDe($reporte)['pasos']);
});

test('poc null is valid', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
        'poc' => null,
    ]);

    $reporte = Reporte::where('investigador_id', $user->id)->first();
    $this->assertNull($reporte->poc);
});

test('vector_cvss is optional', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test',
        'descripcion' => 'Test',
    ]);

    $reporte = Reporte::where('investigador_id', $user->id)->first();
    $this->assertNull($reporte->vector_cvss);
});

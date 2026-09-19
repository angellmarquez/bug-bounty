<?php

use App\Models\EventoReporte;
use App\Models\Programa;

test('guests are redirected to the login page', function () {
    $reporte = reporteDe(investigador());
    $response = $this->get(route('reportes.show', $reporte));
    $response->assertRedirect(route('login'));
});

test('investigador can view own reporte', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('reportes/Show'));
});

test('investigador cannot view reporte de otro', function () {
    $user = investigador();
    $this->actingAs($user);

    $otro = investigador();
    $reporteAjeno = reporteDe($otro);

    $response = $this->get(route('reportes.show', $reporteAjeno));
    $response->assertForbidden();
});

test('gestion can view non-borrador reporte', function () {
    $user = gestion();
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado']);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
});

test('gestion can view notas internas', function () {
    $user = gestion();
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), null, [
        'estado' => 'enviado',
        'notas_internas' => 'Nota confidencial de gestion',
    ]);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();
    $this->assertTrue($props['puedeVerNotasInternas']);
    $this->assertEquals('Nota confidencial de gestion', $props['reporte']['notas_internas']);
});

test('admin can view any reporte', function () {
    $user = administrador();
    $this->actingAs($user);

    $reporte = reporteDe(investigador());

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
});

test('admin can view notas internas', function () {
    $user = administrador();
    $this->actingAs($user);

    $reporte = reporteDe(investigador(), null, [
        'notas_internas' => 'Nota de administrador',
    ]);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();
    $this->assertTrue($props['puedeVerNotasInternas']);
});

test('investigador does not see notas internas', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, [
        'notas_internas' => 'Nota privada',
    ]);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();
    $this->assertFalse($props['puedeVerNotasInternas']);
    $this->assertArrayNotHasKey('notas_internas', $props['reporte']);
});

test('reporte show includes timeline eventos', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user);
    EventoReporte::factory()->create([
        'reporte_id' => $reporte->id,
        'tipo' => 'creado',
        'nota' => 'Reporte creado por el investigador',
    ]);
    EventoReporte::factory()->create([
        'reporte_id' => $reporte->id,
        'tipo' => 'enviado',
        'nota' => 'Enviado para revision',
    ]);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertCount(2, $props['reporte']['eventos']);
    $this->assertEquals('creado', $props['reporte']['eventos'][0]['tipo']);
});

test('reporte show includes actor in eventos', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user);
    EventoReporte::factory()->create([
        'reporte_id' => $reporte->id,
        'actor_id' => $user->id,
        'tipo' => 'creado',
    ]);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertNotNull($props['reporte']['eventos'][0]['actor']);
    $this->assertEquals($user->id, $props['reporte']['eventos'][0]['actor']['id']);
});

test('reporte show includes relaciones', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create(['nombre' => 'Programa Test']);
    $reporte = reporteDe($user, $programa);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertNotNull($props['reporte']['programa']);
    $this->assertEquals('Programa Test', $props['reporte']['programa']['nombre']);
    $this->assertNotNull($props['reporte']['investigador']);
    $this->assertEquals($user->id, $props['reporte']['investigador']['id']);
});

test('reporte duplicado includes duplicadoDe', function () {
    $user = investigador();
    $this->actingAs($user);

    $original = reporteDe($user);
    $duplicado = reporteDe($user, null, [
        'es_duplicado_de' => $original->id,
    ]);

    $response = $this->get(route('reportes.show', $duplicado));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertNotNull($props['reporte']['duplicadoDe']);
    $this->assertEquals($original->id, $props['reporte']['duplicadoDe']['id']);
    $this->assertEquals($original->numero_reporte, $props['reporte']['duplicadoDe']['numero_reporte']);
});

test('reporte inexistente returns 404', function () {
    $user = investigador();
    $this->actingAs($user);

    $response = $this->get(route('reportes.show', 99999));
    $response->assertNotFound();
});

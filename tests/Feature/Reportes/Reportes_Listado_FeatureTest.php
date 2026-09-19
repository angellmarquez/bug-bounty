<?php

use App\Models\Programa;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('reportes.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit reportes index', function () {
    $user = investigador();
    $this->actingAs($user);

    $response = $this->get(route('reportes.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('reportes/Index'));
});

test('investigador sees only own reportes', function () {
    $user = investigador();
    $this->actingAs($user);

    $propio = reporteDe($user);
    $ajeno = reporteDe(investigador());

    $response = $this->get(route('reportes.index'));
    $response->assertOk();
    $props = $response->inertiaProps();

    $ids = collect($props['reportes']['data'])->pluck('id')->toArray();
    $this->assertContains($propio->id, $ids);
    $this->assertNotContains($ajeno->id, $ids);
});

test('gestion sees all non-borrador reportes', function () {
    $user = gestion();
    $this->actingAs($user);

    $enviado = reporteDe(investigador(), null, ['estado' => 'enviado']);
    $borrador = reporteDe(investigador(), null, ['estado' => 'borrador']);

    $response = $this->get(route('reportes.index'));
    $response->assertOk();
    $props = $response->inertiaProps();

    $ids = collect($props['reportes']['data'])->pluck('id')->toArray();
    $this->assertContains($enviado->id, $ids);
    $this->assertNotContains($borrador->id, $ids);
});

test('admin sees all reportes including borradores', function () {
    $user = administrador();
    $this->actingAs($user);

    $enviado = reporteDe(investigador(), null, ['estado' => 'enviado']);
    $borrador = reporteDe(investigador(), null, ['estado' => 'borrador']);

    $response = $this->get(route('reportes.index'));
    $response->assertOk();
    $props = $response->inertiaProps();

    $ids = collect($props['reportes']['data'])->pluck('id')->toArray();
    $this->assertContains($enviado->id, $ids);
    $this->assertContains($borrador->id, $ids);
});

test('filter by estado works', function () {
    $user = administrador();
    $this->actingAs($user);

    reporteDe(investigador(), null, ['estado' => 'enviado']);
    reporteDe(investigador(), null, ['estado' => 'validado']);

    $response = $this->get(route('reportes.index', ['estado' => 'enviado']));
    $response->assertOk();
    $props = $response->inertiaProps();

    foreach ($props['reportes']['data'] as $reporte) {
        $this->assertEquals('enviado', $reporte['estado']);
    }
});

test('filter by severidad works', function () {
    $user = administrador();
    $this->actingAs($user);

    reporteDe(investigador(), null, ['severidad' => 'critica']);
    reporteDe(investigador(), null, ['severidad' => 'baja']);

    $response = $this->get(route('reportes.index', ['severidad' => 'critica']));
    $response->assertOk();
    $props = $response->inertiaProps();

    foreach ($props['reportes']['data'] as $reporte) {
        $this->assertEquals('critica', $reporte['severidad']);
    }
});

test('search by titulo works', function () {
    $user = administrador();
    $this->actingAs($user);

    reporteDe(investigador(), null, ['titulo' => 'Vulnerabilidad XSS en login']);
    reporteDe(investigador(), null, ['titulo' => 'SQL Injection en API']);

    $response = $this->get(route('reportes.index', ['busqueda' => 'XSS']));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertCount(1, $props['reportes']['data']);
    $this->assertStringContainsString('XSS', $props['reportes']['data'][0]['titulo']);
});

test('search by numero_reporte works', function () {
    $user = administrador();
    $this->actingAs($user);

    reporteDe(investigador(), null, ['numero_reporte' => 'BB-2026-0001']);
    reporteDe(investigador(), null, ['numero_reporte' => 'BB-2026-0002']);

    $response = $this->get(route('reportes.index', ['busqueda' => 'BB-2026-0001']));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertCount(1, $props['reportes']['data']);
    $this->assertEquals('BB-2026-0001', $props['reportes']['data'][0]['numero_reporte']);
});

test('pagination works with 15 items per page', function () {
    $user = administrador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();
    for ($i = 0; $i < 20; $i++) {
        reporteDe(investigador(), $programa);
    }

    $response = $this->get(route('reportes.index'));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertCount(15, $props['reportes']['data']);
    $this->assertEquals(20, $props['reportes']['total']);
    $this->assertEquals(2, $props['reportes']['last_page']);
});

test('reportes index returns programas for filter', function () {
    $user = administrador();
    $this->actingAs($user);

    Programa::factory()->create(['nombre' => 'Programa Alpha']);
    Programa::factory()->create(['nombre' => 'Programa Beta']);

    $response = $this->get(route('reportes.index'));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertArrayHasKey('programas', $props);
    $this->assertCount(2, $props['programas']);
});

test('investigador programas filter only shows active public ones', function () {
    $user = investigador();
    $this->actingAs($user);

    Programa::factory()->create(['nombre' => 'Activo Publico', 'estado' => 'activo', 'es_publico' => true]);
    Programa::factory()->create(['nombre' => 'Inactivo', 'estado' => 'borrador', 'es_publico' => true]);
    Programa::factory()->create(['nombre' => 'Privado', 'estado' => 'activo', 'es_publico' => false]);

    $response = $this->get(route('reportes.index'));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertCount(1, $props['programas']);
    $this->assertEquals('Activo Publico', $props['programas'][0]['nombre']);
});

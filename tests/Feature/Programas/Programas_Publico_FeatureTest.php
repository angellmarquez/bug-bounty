<?php

use App\Models\Programa;

test('investigador can see active public program', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]);

    $response = $this->get(route('programas.show', $programa));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('programas/Show'));
});

test('investigador can see en_pausa public program', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create(['estado' => 'en_pausa', 'es_publico' => true]);

    $response = $this->get(route('programas.show', $programa));
    $response->assertOk();
});

test('investigador cannot see archivado program', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create(['estado' => 'archivado', 'es_publico' => true]);

    $response = $this->get(route('programas.show', $programa));
    $response->assertForbidden();
});

test('investigador cannot see private program', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create(['estado' => 'activo', 'es_publico' => false]);

    $response = $this->get(route('programas.show', $programa));
    $response->assertForbidden();
});

test('investigador cannot see borrador program', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create(['estado' => 'borrador', 'es_publico' => true]);

    $response = $this->get(route('programas.show', $programa));
    $response->assertForbidden();
});

test('gestion can see own borrador program', function () {
    $user = gestion();
    $this->actingAs($user);

    $programa = programaDe($user, ['estado' => 'borrador']);

    $response = $this->get(route('programas.gestion'));
    $response->assertOk();
    $props = $response->inertiaProps();

    $ids = collect($props['programas']['data'])->pluck('id')->toArray();
    $this->assertContains($programa->id, $ids);
});

test('admin can see any program', function () {
    $user = administrador();
    $this->actingAs($user);

    $programa = Programa::factory()->create(['estado' => 'archivado', 'es_publico' => false]);

    $response = $this->get(route('programas.show', $programa));
    $response->assertOk();
});

test('public index filters by estado', function () {
    $user = investigador();
    $this->actingAs($user);

    $activo = Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]);
    $en_pausa = Programa::factory()->create(['estado' => 'en_pausa', 'es_publico' => true]);
    Programa::factory()->create(['estado' => 'borrador', 'es_publico' => true]);
    Programa::factory()->create(['estado' => 'activo', 'es_publico' => false]);

    $response = $this->get(route('programas.index', ['estado' => 'activo']));
    $response->assertOk();
    $props = $response->inertiaProps();

    $ids = collect($props['programas']['data'])->pluck('id')->toArray();
    $this->assertContains($activo->id, $ids);
    $this->assertNotContains($en_pausa->id, $ids);
});

test('public index search by name works', function () {
    $user = administrador();
    $this->actingAs($user);

    Programa::factory()->create(['nombre' => 'BugBounty Corp']);
    Programa::factory()->create(['nombre' => 'GovSecure']);

    $response = $this->get(route('programas.index', ['busqueda' => 'BugBounty']));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertCount(1, $props['programas']['data']);
    $this->assertEquals('BugBounty Corp', $props['programas']['data'][0]['nombre']);
});

test('investigador sees reportar button when program is active public', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]);

    $response = $this->get(route('programas.show', $programa));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertTrue($props['puedeReportar']);
});

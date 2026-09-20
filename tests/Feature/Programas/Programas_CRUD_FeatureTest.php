<?php

use App\Models\Programa;

test('guest is redirected to login', function () {
    $response = $this->get(route('programas.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated user can visit programas index', function () {
    $user = investigador();
    $this->actingAs($user);

    $response = $this->get(route('programas.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('programas/Index'));
});

test('investigador can see public active programs', function () {
    $user = investigador();
    $this->actingAs($user);

    $activo = Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]);
    Programa::factory()->create(['estado' => 'borrador', 'es_publico' => true]);
    Programa::factory()->create(['estado' => 'activo', 'es_publico' => false]);

    $response = $this->get(route('programas.index'));
    $response->assertOk();
    $props = $response->inertiaProps();

    $ids = collect($props['programas']['data'])->pluck('id')->toArray();
    $this->assertContains($activo->id, $ids);
    $this->assertCount(1, $props['programas']['data']);
});

test('gestion can create a program', function () {
    $user = gestion();
    $this->actingAs($user);

    $response = $this->post(route('programas.store'), [
        'nombre' => 'New Program',
        'descripcion' => 'Program description',
        'recompensa_min' => 100,
        'recompensa_max' => 1000,
        'moneda' => 'USD',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('programas', ['nombre' => 'New Program', 'estado' => 'borrador']);
});

test('investigador cannot create a program', function () {
    $user = investigador();
    $this->actingAs($user);

    $response = $this->post(route('programas.store'), [
        'nombre' => 'Test Program',
        'descripcion' => 'Test description',
        'recompensa_min' => 50,
        'recompensa_max' => 500,
        'moneda' => 'USD',
    ]);

    $response->assertForbidden();
});

test('gestion can update own program', function () {
    $user = gestion();
    $this->actingAs($user);

    $programa = programaDe($user);

    $response = $this->put(route('programas.update', $programa), [
        'nombre' => 'Updated Name',
        'descripcion' => 'Updated description',
        'recompensa_min' => 100,
        'recompensa_max' => 1000,
        'moneda' => 'USD',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('programas', ['id' => $programa->id, 'nombre' => 'Updated Name']);
});

test('gestion cannot update another gestion program', function () {
    $user = gestion();
    $this->actingAs($user);

    $otro = gestion();
    $programa = programaDe($otro);

    $response = $this->put(route('programas.update', $programa), [
        'nombre' => 'Hacked Name',
        'descripcion' => 'Hacked',
        'recompensa_min' => 0,
        'recompensa_max' => 1,
        'moneda' => 'USD',
    ]);

    $response->assertForbidden();
});

test('admin can delete a program', function () {
    $user = administrador();
    $this->actingAs($user);

    $programa = programaDe(gestion());

    $response = $this->delete(route('programas.destroy', $programa));
    $response->assertRedirect();
    $this->assertSoftDeleted('programas', ['id' => $programa->id]);
});

test('gestion cannot delete a program', function () {
    $user = gestion();
    $this->actingAs($user);

    $programa = programaDe($user);

    $response = $this->delete(route('programas.destroy', $programa));
    $response->assertForbidden();
});

test('gestion sees only own programs in gestion index', function () {
    $user = gestion();
    $this->actingAs($user);

    $propio = programaDe($user);
    $ajeno = programaDe(gestion());

    $response = $this->get(route('programas.gestion'));
    $response->assertOk();
    $props = $response->inertiaProps();

    $ids = collect($props['programas']['data'])->pluck('id')->toArray();
    $this->assertContains($propio->id, $ids);
    $this->assertNotContains($ajeno->id, $ids);
});

test('slug is auto-generated from name', function () {
    $user = gestion();
    $this->actingAs($user);

    $response = $this->post(route('programas.store'), [
        'nombre' => 'Mi Programa de Prueba',
        'descripcion' => 'Description',
        'recompensa_min' => 50,
        'recompensa_max' => 500,
        'moneda' => 'USD',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('programas', ['nombre' => 'Mi Programa de Prueba', 'slug' => 'mi-programa-de-prueba']);
});

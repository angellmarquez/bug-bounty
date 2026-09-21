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
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('programas', ['nombre' => 'Mi Programa de Prueba', 'slug' => 'mi-programa-de-prueba']);
});

test('programas con el mismo nombre reciben un slug distinto en lugar de fallar con error 500', function () {
    $this->actingAs(gestion());
    $datos = [
        'nombre' => 'Programa Repetido',
        'descripcion' => 'Description',
    ];

    $this->post(route('programas.store'), $datos)->assertRedirect();
    $this->post(route('programas.store'), $datos)->assertRedirect();

    $this->assertDatabaseHas('programas', ['slug' => 'programa-repetido']);
    $this->assertDatabaseHas('programas', ['slug' => 'programa-repetido-2']);

    // Un programa eliminado (soft delete) conserva su slug, así que también cuenta.
    Programa::where('slug', 'programa-repetido-2')->firstOrFail()->delete();
    $this->post(route('programas.store'), $datos)->assertRedirect();

    $this->assertDatabaseHas('programas', ['slug' => 'programa-repetido-3']);
});

test('renombrar un programa a un nombre ya usado no rompe por slug duplicado', function () {
    $this->actingAs(administrador());
    Programa::factory()->create(['nombre' => 'Nombre Libre', 'slug' => 'nombre-libre']);
    $otro = Programa::factory()->create(['nombre' => 'Otro Nombre']);

    $otro->update(['nombre' => 'Nombre Libre']);

    expect($otro->fresh()->slug)->toBe('nombre-libre-2');
});

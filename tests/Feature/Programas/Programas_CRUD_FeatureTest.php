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

test('una empresa aprobada puede crear un programa', function () {
    $user = propietarioDeEmpresa();
    $this->actingAs($user);

    $response = $this->post(route('programas.store'), [
        'nombre' => 'New Program',
        'descripcion' => 'Program description',
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.ejemplo.test']],
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

test('la empresa puede editar su propio programa', function () {
    $user = propietarioDeEmpresa();
    $this->actingAs($user);

    $programa = programaDeEmpresa($user);

    $response = $this->put(route('programas.update', $programa), [
        'nombre' => 'Updated Name',
        'descripcion' => 'Updated description',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('programas', ['id' => $programa->id, 'nombre' => 'Updated Name']);
});

test('una empresa no puede editar el programa de otra empresa', function () {
    $user = propietarioDeEmpresa();
    $this->actingAs($user);

    $otro = propietarioDeEmpresa();
    $programa = programaDeEmpresa($otro);

    $response = $this->put(route('programas.update', $programa), [
        'nombre' => 'Hacked Name',
        'descripcion' => 'Hacked',
    ]);

    $response->assertForbidden();
});

test('admin no puede borrar programas: eso lo decide la empresa dueña', function () {
    $user = administrador();
    $this->actingAs($user);

    $programa = programaDeEmpresa(propietarioDeEmpresa());

    $response = $this->delete(route('programas.destroy', $programa));
    $response->assertForbidden();
    $this->assertDatabaseHas('programas', ['id' => $programa->id, 'deleted_at' => null]);
});

test('un moderador no puede borrar un programa', function () {
    $user = moderador();
    $this->actingAs($user);

    $programa = programaDeEmpresa(propietarioDeEmpresa());

    $response = $this->delete(route('programas.destroy', $programa));
    $response->assertForbidden();
});

test('la empresa solo ve sus propios programas en la gestion de programas', function () {
    $user = propietarioDeEmpresa();
    $this->actingAs($user);

    $propio = programaDeEmpresa($user);
    $ajeno = programaDeEmpresa(propietarioDeEmpresa());

    $response = $this->get(route('programas.gestion'));
    $response->assertOk();
    $props = $response->inertiaProps();

    $ids = collect($props['programas']['data'])->pluck('id')->toArray();
    $this->assertContains($propio->id, $ids);
    $this->assertNotContains($ajeno->id, $ids);
});

test('slug is auto-generated from name', function () {
    $user = propietarioDeEmpresa();
    $this->actingAs($user);

    $response = $this->post(route('programas.store'), [
        'nombre' => 'Mi Programa de Prueba',
        'descripcion' => 'Description',
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.ejemplo.test']],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('programas', ['nombre' => 'Mi Programa de Prueba', 'slug' => 'mi-programa-de-prueba']);
});

test('programas con el mismo nombre reciben un slug distinto en lugar de fallar con error 500', function () {
    $this->actingAs(propietarioDeEmpresa());
    $datos = [
        'nombre' => 'Programa Repetido',
        'descripcion' => 'Description',
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.ejemplo.test']],
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

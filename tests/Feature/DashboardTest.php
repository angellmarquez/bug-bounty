<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard renders Dashboard page via Inertia', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('dashboard returns stats for authenticated user', function () {
    $user = User::factory()->create(['reputation_score' => 100]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $props = $response->inertiaProps();
    $this->assertArrayHasKey('stats', $props);
    $this->assertArrayHasKey('reportes_total', $props['stats']);
});

test('dashboard includes user roles in props', function () {
    $user = conRol(User::factory()->create(), 'investigador');
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $props = $response->inertiaProps();
    $this->assertArrayHasKey('userRoles', $props);
    $this->assertContains('investigador', $props['userRoles']);
});

test('admin user has administrador role in dashboard props', function () {
    $user = administrador();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $props = $response->inertiaProps();
    $this->assertContains('administrador', $props['userRoles']);
});

test('moderador user has moderador role in dashboard props', function () {
    $user = moderador();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $props = $response->inertiaProps();
    $this->assertContains('moderador', $props['userRoles']);
});

test('investigador sees only own reportes in stats', function () {
    $user = investigador();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $props = $response->inertiaProps();
    $this->assertEquals(0, $props['stats']['reportes_total']);
});

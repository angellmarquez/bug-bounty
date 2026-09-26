<?php

use App\Models\Programa;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::forget('inicio.publico'));

test('la landing es pública y muestra cifras reales', function () {
    investigador();
    Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('cifras.programas', 1)
            ->where('cifras.investigadores', 1)
            ->has('programas', 1));
});

test('la landing solo enseña programas públicos y activos', function () {
    Programa::factory()->create(['nombre' => 'Visible', 'estado' => 'activo', 'es_publico' => true]);
    Programa::factory()->create(['nombre' => 'Privado', 'estado' => 'activo', 'es_publico' => false]);
    Programa::factory()->create(['nombre' => 'Borrador', 'estado' => 'borrador', 'es_publico' => true]);

    $programas = $this->get('/')->inertiaProps()['programas'];

    expect(collect($programas)->pluck('nombre')->all())->toBe(['Visible']);
});

test('la landing no expone datos cifrados de los programas', function () {
    Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]);

    $programa = $this->get('/')->inertiaProps()['programas'][0];

    expect($programa)->toHaveKeys(['id', 'nombre', 'empresa', 'nivel_acceso', 'tipos'])
        ->and($programa)->not->toHaveKeys(['descripcion', 'bugs_buscados', 'poc_schema']);
});

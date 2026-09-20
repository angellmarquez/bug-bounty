<?php

use App\Models\Programa;
use App\Models\Reporte;
use App\Services\Pgp\PgpService;

test('description and poc are encrypted internally without a user supplied pgp key', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test PGP interno',
        'descripcion' => 'Descripcion sensible cifrada internamente',
        'poc' => ['paso' => 'ejecutar'],
    ]);

    $reporte = Reporte::where('investigador_id', $user->id)->firstOrFail();
    $pgp = app(PgpService::class);

    expect($reporte->getRawOriginal('descripcion'))->not->toBe('Descripcion sensible cifrada internamente');
    expect($reporte->getRawOriginal('poc'))->not->toBeNull()->not->toContain('ejecutar');
    expect($reporte->clave_huella)->toBe($pgp->platformKey()->huella);

    $descifrado = $pgp->descifrarReporte($reporte->getRawOriginal('descripcion'), $reporte->getRawOriginal('poc'));
    expect($descifrado['descripcion'])->toBe('Descripcion sensible cifrada internamente');
    expect($descifrado['poc'])->toBe(['paso' => 'ejecutar']);
});

test('only the report author can view its private content', function () {
    $author = investigador();
    $this->actingAs($author);
    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Contenido privado',
        'descripcion' => 'Solo debe verlo el autor',
        'poc' => ['evidencia' => 'privada'],
    ]);

    $reporte = Reporte::where('investigador_id', $author->id)->firstOrFail();

    $this->get(route('reportes.show', $reporte))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporte.descripcion', 'Solo debe verlo el autor')
            ->where('reporte.poc', ['evidencia' => 'privada']));

    $this->actingAs(investigador())
        ->get(route('reportes.show', $reporte))
        ->assertForbidden();
});

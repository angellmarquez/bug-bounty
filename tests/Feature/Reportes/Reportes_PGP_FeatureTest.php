<?php

use App\Models\Programa;
use App\Models\Reporte;
use App\Services\Pgp\PgpService;

beforeEach(function () {
    app(PgpService::class)->generatePlatformKeyPair();
});

test('description is encrypted internally without a user supplied pgp key', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create();

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Test PGP interno',
        'descripcion' => 'Descripcion sensible cifrada internamente',
        'poc' => ['paso' => 'ejecutar'],
    ]);

    $reporte = Reporte::where('investigador_id', $user->id)->first();
    expect($reporte->getRawOriginal('descripcion_cifrada'))
        ->not->toBe('Descripcion sensible cifrada internamente');
    expect($reporte->getRawOriginal('poc'))->toBeNull();
    expect($reporte->getRawOriginal('poc_cifrado'))->not->toBeNull();
});

test('only the report author can decrypt its private content', function () {
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

    $this->withHeaders(['X-Inertia' => 'true'])
        ->get(route('reportes.show', $reporte))
        ->assertOk();
    expect(app(PgpService::class)->decrypt($reporte->getRawOriginal('descripcion_cifrada')))
        ->toBe('Solo debe verlo el autor');

    $this->actingAs(investigador())
        ->get(route('reportes.show', $reporte))
        ->assertForbidden();
});

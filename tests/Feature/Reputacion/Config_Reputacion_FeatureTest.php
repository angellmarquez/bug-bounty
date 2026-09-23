<?php

use App\Enums\GravedadSancion;
use App\Models\ConfiguracionReputacion;
use App\Providers\ReputacionServiceProvider;
use App\Services\Reputacion\ReputationService;

test('el admin guarda la config y el cambio persiste en la base, no solo en la auditoria', function () {
    $this->actingAs(administrador());

    $this->put(route('admin.config.reputacion.update'), [
        'puntos_inicial' => 10,
        'puntos' => [
            'reporte_validado' => 999,
            'reporte_resuelto' => 100,
            'calidad_documentacion' => 10,
            'participacion' => 5,
        ],
        'penalizacion' => ['leve' => -25, 'media' => -80, 'grave' => -250],
        'suspension' => ['leve' => ['dias' => 0], 'media' => ['dias' => 7], 'grave' => ['dias' => 30]],
        'plazo_apelacion_dias' => 7,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('configuraciones_reputacion', [
        'puntos_inicial' => 10,
        'puntos_reporte_validado' => 999,
    ]);
    $this->assertDatabaseHas('auditorias', ['accion' => 'admin.config.reputacion_actualizada']);
});

test('el nuevo valor se usa de inmediato para otorgar puntos, no el default de fabrica', function () {
    ConfiguracionReputacion::factory()->create(['puntos_reporte_validado' => 777]);
    $this->actingAs(administrador());

    // Simula el arranque de la app tras guardar: el provider vuelve a cargar la config.
    app()->register(ReputacionServiceProvider::class, force: true);

    $usuario = investigador();
    app(ReputationService::class)->otorgarPuntosEvento($usuario, 'reporte_validado');

    expect($usuario->fresh()->reputation_score)->toBe(777);
});

test('rechaza una penalizacion positiva: seria un premio, no un castigo', function () {
    $this->actingAs(administrador());

    $this->put(route('admin.config.reputacion.update'), [
        'puntos_inicial' => 0,
        'puntos' => ['reporte_validado' => 50, 'reporte_resuelto' => 100, 'calidad_documentacion' => 10, 'participacion' => 5],
        'penalizacion' => ['leve' => -25, 'media' => -80, 'grave' => 50],
        'suspension' => ['leve' => ['dias' => 0], 'media' => ['dias' => 7], 'grave' => ['dias' => 30]],
        'plazo_apelacion_dias' => 7,
    ])->assertSessionHasErrors('penalizacion.grave');

    expect(ConfiguracionReputacion::count())->toBe(0);
});

test('la penalizacion configurada se aplica en la sancion real', function () {
    ConfiguracionReputacion::factory()->create(['penalizacion_grave' => -321]);
    app()->register(ReputacionServiceProvider::class, force: true);

    $investigador = investigador();
    $sancion = app(ReputationService::class)->aplicarSancion($investigador, 'fabricacion', GravedadSancion::Grave);

    expect($sancion->puntos)->toBe(-321);
});

test('solo el administrador actualiza la configuracion de reputacion', function () {
    $this->actingAs(investigador());

    $this->put(route('admin.config.reputacion.update'), [
        'puntos_inicial' => 0,
        'puntos' => ['reporte_validado' => 50, 'reporte_resuelto' => 100, 'calidad_documentacion' => 10, 'participacion' => 5],
        'penalizacion' => ['leve' => -25, 'media' => -80, 'grave' => -250],
        'suspension' => ['leve' => ['dias' => 0], 'media' => ['dias' => 7], 'grave' => ['dias' => 30]],
        'plazo_apelacion_dias' => 7,
    ])->assertForbidden();

    expect(ConfiguracionReputacion::count())->toBe(0);
});

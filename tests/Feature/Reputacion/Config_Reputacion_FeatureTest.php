<?php

use App\Enums\GravedadSancion;
use App\Models\ConfiguracionReputacion;
use App\Models\Programa;
use App\Models\User;
use App\Providers\ReputacionServiceProvider;
use App\Services\Reputacion\ReputationService;

/**
 * Datos del formulario de /admin/config/reputacion, con los cambios de cada test.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function configReputacion(array $cambios = []): array
{
    return array_replace_recursive([
        'puntos_inicial' => 0,
        'puntos_por_severidad' => [
            'critica' => ['reporte_validado' => 100, 'reporte_resuelto' => 200],
            'alta' => ['reporte_validado' => 50, 'reporte_resuelto' => 100],
            'media' => ['reporte_validado' => 25, 'reporte_resuelto' => 50],
            'baja' => ['reporte_validado' => 10, 'reporte_resuelto' => 20],
            'ninguna' => ['reporte_validado' => 5, 'reporte_resuelto' => 10],
        ],
        'puntos' => ['reporte_validado' => 50, 'reporte_resuelto' => 100],
        'penalizacion' => ['leve' => -25, 'media' => -80, 'grave' => -250],
        'suspension' => ['leve' => ['dias' => 0], 'media' => ['dias' => 7], 'grave' => ['dias' => 30]],
        'plazo_apelacion_dias' => 7,
    ], $cambios);
}

test('el admin guarda la config y el cambio persiste en la base, no solo en la auditoria', function () {
    $this->actingAs(administrador());

    $this->put(route('admin.config.reputacion.update'), configReputacion([
        'puntos_inicial' => 10,
        'puntos' => ['reporte_validado' => 999],
        'puntos_por_severidad' => ['critica' => ['reporte_resuelto' => 500]],
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $guardada = ConfiguracionReputacion::query()->firstOrFail();
    expect($guardada->puntos_inicial)->toBe(10)
        ->and($guardada->puntos_reporte_validado)->toBe(999)
        ->and($guardada->puntos_por_severidad['critica']['reporte_resuelto'])->toBe(500);
    $this->assertDatabaseHas('auditorias', ['accion' => 'admin.config.reputacion_actualizada']);
});

test('los puntos por severidad guardados son los que recibe un informe con CVSS', function () {
    $this->actingAs(administrador())
        ->put(route('admin.config.reputacion.update'), configReputacion([
            'puntos_por_severidad' => ['alta' => ['reporte_validado' => 321]],
        ]))->assertSessionHasNoErrors();

    // Simula el arranque de la app tras guardar: el provider vuelve a cargar la config.
    app()->register(ReputacionServiceProvider::class, force: true);

    $investigador = investigador();
    $reporte = reporteDe($investigador, Programa::factory()->create(), ['estado' => 'validado', 'severidad' => 'alta']);
    app(ReputationService::class)->otorgarPuntosEvento($investigador, 'reporte_validado', $reporte);

    expect($investigador->fresh()->reputation_score)->toBe(321);
});

test('el valor sin severidad se usa para los informes sin CVSS', function () {
    ConfiguracionReputacion::factory()->create(['puntos_reporte_validado' => 777]);
    app()->register(ReputacionServiceProvider::class, force: true);

    $usuario = investigador();
    app(ReputationService::class)->otorgarPuntosEvento($usuario, 'reporte_validado');

    expect($usuario->fresh()->reputation_score)->toBe(777);
});

test('sin tabla por severidad guardada se conservan los valores de fabrica', function () {
    ConfiguracionReputacion::factory()->create(['puntos_por_severidad' => null]);
    app()->register(ReputacionServiceProvider::class, force: true);

    expect(config('reputacion.puntos_por_severidad.critica.reporte_resuelto'))->toBe(200);
});

test('los puntos iniciales se dan a quien se registra', function () {
    ConfiguracionReputacion::factory()->create(['puntos_inicial' => 40]);
    app()->register(ReputacionServiceProvider::class, force: true);

    $this->post(route('register.store'), [
        'name' => 'Nueva Investigadora',
        'email' => 'nueva@test.com',
        'password' => 'Clave-Segura-2026',
        'password_confirmation' => 'Clave-Segura-2026',
        'terminos' => '1',
    ])->assertSessionHasNoErrors();

    $usuario = User::query()->where('email', 'nueva@test.com')->firstOrFail();
    expect($usuario->reputation_score)->toBe(40);
    $this->assertDatabaseHas('ledger_reputacion', ['usuario_id' => $usuario->id, 'motivo' => 'puntos_iniciales', 'puntos' => 40]);
});

test('con 0 puntos iniciales no se crea ningun movimiento', function () {
    $this->post(route('register.store'), [
        'name' => 'Sin Puntos',
        'email' => 'sinpuntos@test.com',
        'password' => 'Clave-Segura-2026',
        'password_confirmation' => 'Clave-Segura-2026',
        'terminos' => '1',
    ])->assertSessionHasNoErrors();

    $usuario = User::query()->where('email', 'sinpuntos@test.com')->firstOrFail();
    $this->assertDatabaseMissing('ledger_reputacion', ['usuario_id' => $usuario->id]);
});

test('rechaza una penalizacion positiva: seria un premio, no un castigo', function () {
    $this->actingAs(administrador());

    $this->put(route('admin.config.reputacion.update'), configReputacion(['penalizacion' => ['grave' => 50]]))
        ->assertSessionHasErrors('penalizacion.grave');

    expect(ConfiguracionReputacion::count())->toBe(0);
});

test('rechaza puntos negativos, severidades inventadas o una tabla incompleta', function (array $datos, string $campo) {
    $this->actingAs(administrador());

    $this->put(route('admin.config.reputacion.update'), $datos)->assertSessionHasErrors($campo);

    expect(ConfiguracionReputacion::count())->toBe(0);
})->with([
    'puntos negativos' => [fn () => configReputacion(['puntos_por_severidad' => ['media' => ['reporte_validado' => -5]]]), 'puntos_por_severidad.media.reporte_validado'],
    'severidad inventada' => [fn () => configReputacion(['puntos_por_severidad' => ['extrema' => ['reporte_validado' => 1, 'reporte_resuelto' => 1]]]), 'puntos_por_severidad'],
    'falta una severidad' => [function () {
        $datos = configReputacion();
        unset($datos['puntos_por_severidad']['critica']);

        return $datos;
    }, 'puntos_por_severidad.critica'],
]);

test('la penalizacion configurada se aplica en la sancion real', function () {
    ConfiguracionReputacion::factory()->create(['penalizacion_grave' => -321]);
    app()->register(ReputacionServiceProvider::class, force: true);

    $investigador = investigador();
    $sancion = app(ReputationService::class)->aplicarSancion($investigador, 'fabricacion', GravedadSancion::Grave);

    expect($sancion->puntos)->toBe(-321);
});

test('solo el administrador actualiza la configuracion de reputacion', function () {
    $this->actingAs(investigador());

    $this->put(route('admin.config.reputacion.update'), configReputacion())->assertForbidden();

    expect(ConfiguracionReputacion::count())->toBe(0);
});

test('rechaza valores desorbitados en la configuracion', function (array $cambios, string $campo) {
    $this->actingAs(administrador());

    $this->put(route('admin.config.reputacion.update'), configReputacion($cambios))->assertSessionHasErrors($campo);

    expect(ConfiguracionReputacion::query()->exists())->toBeFalse();
})->with([
    'penalizacion enorme' => [['penalizacion' => ['grave' => -999999999]], 'penalizacion.grave'],
    'puntos enormes' => [['puntos_por_severidad' => ['critica' => ['reporte_validado' => 999999]]], 'puntos_por_severidad.critica.reporte_validado'],
    'puntos iniciales enormes' => [['puntos_inicial' => 50000], 'puntos_inicial'],
    'suspension de mas de un año' => [['suspension' => ['grave' => ['dias' => 400]]], 'suspension.grave.dias'],
    'plazo de apelacion excesivo' => [['plazo_apelacion_dias' => 365], 'plazo_apelacion_dias'],
]);

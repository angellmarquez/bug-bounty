<?php

use App\Enums\GravedadSancion;
use App\Models\Empresa;
use App\Models\Programa;
use App\Models\User;
use App\Services\Reputacion\ReputationService;

function cuentaDe(User $usuario, string $ruta = 'dashboard'): array
{
    return test()->actingAs($usuario)->get(route($ruta))->viewData('page')['props']['cuenta'];
}

test('un investigador ve su rango y ningún alcance de moderador', function () {
    $cuenta = cuentaDe(investigador(['reputation_score' => 150]));

    expect(array_column($cuenta['roles'], 'slug'))->toBe(['investigador'])
        ->and($cuenta['rango']['clave'])->toBe('plata')
        ->and($cuenta['rango']['siguiente']['clave'])->toBe('oro')
        ->and($cuenta['moderador'])->toBeNull()
        ->and($cuenta['suspension'])->toBeNull()
        ->and($cuenta['empresa'])->toBeNull();
});

test('un investigador que también modera muestra la etiqueta de moderador y sus programas', function () {
    $programa = Programa::factory()->create(['nombre' => 'Programa Moderado']);
    $usuario = conRol(User::factory()->create(), ['investigador', 'moderador']);
    $usuario->programasModerados()->attach($programa->id);

    $cuenta = cuentaDe($usuario);

    expect(array_column($cuenta['roles'], 'slug'))->toBe(['moderador', 'investigador'])
        ->and($cuenta['moderador']['programas'])->toBe([['id' => $programa->id, 'nombre' => 'Programa Moderado']]);
});

test('una suspensión vigente aparece en el estado de la cuenta', function () {
    $inv = investigador();
    app(ReputationService::class)->aplicarSancion($inv, 'fabricacion', GravedadSancion::Media);

    $cuenta = cuentaDe($inv);

    expect($cuenta['suspension'])->toMatchArray(['motivo' => 'fabricacion', 'gravedad' => 'media'])
        ->and($cuenta['suspension']['hasta'])->not->toBeNull();
});

test('una empresa ve el estado de su solicitud y su rol interno', function () {
    $empresa = Empresa::factory()->create(['estado' => 'pendiente', 'razon_social' => 'Acme SA', 'nombre_comercial' => null]);
    $usuario = miembroDeEmpresa($empresa);

    $cuenta = cuentaDe($usuario, 'empresa.dashboard');

    expect($cuenta['empresa'])->toMatchArray(['id' => $empresa->id, 'nombre' => 'Acme SA', 'estado' => 'pendiente', 'rol_interno' => 'propietario']);
});

test('las páginas comparten los rangos y niveles para mostrar insignias', function () {
    $props = $this->actingAs(investigador())->get(route('dashboard'))->viewData('page')['props'];

    expect(array_column($props['reputacionConfig']['rangos'], 'clave'))->toBe(['bronce', 'plata', 'oro', 'platino', 'diamante'])
        ->and(array_column($props['reputacionConfig']['niveles'], 'valor'))->toBe(['bajo', 'medio', 'alto']);
});

test('un visitante sin sesión no recibe estado de cuenta', function () {
    $props = $this->get(route('login'))->viewData('page')['props'];

    expect($props['cuenta'])->toBeNull();
});

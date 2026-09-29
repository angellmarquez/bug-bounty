<?php

use App\Models\Programa;
use Illuminate\Testing\TestResponse;

/** @param  array<string, mixed>  $recompensas */
function crearProgramaConRecompensas(array $recompensas): TestResponse
{
    return test()->actingAs(propietarioDeEmpresa())->post(route('programas.store'), [
        'nombre' => 'Programa con recompensas',
        'descripcion' => 'Programa de prueba para las recompensas por severidad',
        'objetivos' => [['tipo' => 'web', 'valor' => 'https://ejemplo.com']],
        'tiene_recompensas' => true,
        'moneda' => 'USDC',
        ...$recompensas,
    ]);
}

test('una tabla coherente con el rango se guarda', function () {
    crearProgramaConRecompensas([
        'recompensa_min' => 1,
        'recompensa_max' => 5,
        'tabla_recompensas' => ['critica' => 5, 'alta' => 4, 'media' => 2, 'baja' => 1],
    ])->assertSessionHasNoErrors();

    expect(Programa::query()->where('nombre', 'Programa con recompensas')->first()->tabla_recompensas)
        ->toMatchArray(['critica' => 5, 'baja' => 1]);
});

test('rechaza recompensas incoherentes', function (array $recompensas, string $campo) {
    crearProgramaConRecompensas($recompensas)->assertSessionHasErrors($campo);

    expect(Programa::query()->where('nombre', 'Programa con recompensas')->exists())->toBeFalse();
})->with([
    'monto negativo' => [['recompensa_min' => 0, 'recompensa_max' => 5, 'tabla_recompensas' => ['critica' => -50]], 'tabla_recompensas.critica'],
    'monto en texto' => [['recompensa_min' => 0, 'recompensa_max' => 5, 'tabla_recompensas' => ['alta' => 'gratis']], 'tabla_recompensas.alta'],
    'severidad inventada' => [['recompensa_min' => 0, 'recompensa_max' => 5, 'tabla_recompensas' => ['hackeo' => 3]], 'tabla_recompensas'],
    'supera el maximo' => [['recompensa_min' => 1, 'recompensa_max' => 5, 'tabla_recompensas' => ['critica' => 1500]], 'tabla_recompensas'],
    'por debajo del minimo' => [['recompensa_min' => 2, 'recompensa_max' => 5, 'tabla_recompensas' => ['baja' => 1]], 'tabla_recompensas'],
    'baja paga mas que critica' => [['recompensa_min' => 1, 'recompensa_max' => 5, 'tabla_recompensas' => ['critica' => 2, 'baja' => 4]], 'tabla_recompensas'],
    'maximo menor que minimo' => [['recompensa_min' => 5, 'recompensa_max' => 2], 'recompensa_max'],
    'maximo negativo' => [['recompensa_max' => -3], 'recompensa_max'],
    'con recompensas sin maximo' => [[], 'recompensa_max'],
    'moneda que no es USDC' => [['recompensa_max' => 5, 'moneda' => 'BTC'], 'moneda'],
]);

test('las casillas vacias de la tabla no se guardan', function () {
    crearProgramaConRecompensas([
        'recompensa_min' => 1,
        'recompensa_max' => 5,
        'tabla_recompensas' => ['critica' => '5', 'alta' => '', 'media' => '', 'baja' => ''],
    ])->assertSessionHasNoErrors();

    crearProgramaConRecompensas([
        'nombre' => 'Sin tabla',
        'recompensa_max' => 5,
        'tabla_recompensas' => ['critica' => '', 'alta' => '', 'media' => '', 'baja' => ''],
    ])->assertSessionHasNoErrors();

    expect(Programa::query()->where('nombre', 'Programa con recompensas')->first()->tabla_recompensas)->toBe(['critica' => '5'])
        ->and(Programa::query()->where('nombre', 'Sin tabla')->first()->tabla_recompensas)->toBeNull();
});

test('al editar la tabla se compara con el rango ya guardado', function () {
    $duena = propietarioDeEmpresa();
    $programa = programaDeEmpresa($duena, ['tiene_recompensas' => true, 'recompensa_min' => 1, 'recompensa_max' => 5]);

    $this->actingAs($duena)->put(route('programas.update', $programa), [
        'tabla_recompensas' => ['critica' => 50],
    ])->assertSessionHasErrors('tabla_recompensas');

    $this->actingAs($duena)->put(route('programas.update', $programa), [
        'tabla_recompensas' => ['critica' => 5, 'baja' => 1],
    ])->assertSessionHasNoErrors();
});

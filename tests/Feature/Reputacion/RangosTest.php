<?php

use App\Enums\NivelAcceso;
use App\Services\Reputacion\Rangos;

test('los puntos se traducen al rango correspondiente en cada umbral', function (int $puntos, string $rango) {
    expect(app(Rangos::class)->deReputacion($puntos)['clave'])->toBe($rango);
})->with([
    'cero' => [0, 'bronce'],
    'justo antes de plata' => [99, 'bronce'],
    'plata' => [100, 'plata'],
    'justo antes de oro' => [299, 'plata'],
    'oro' => [300, 'oro'],
    'platino' => [700, 'platino'],
    'diamante' => [1500, 'diamante'],
    'muy por encima' => [99999, 'diamante'],
    'negativo' => [-50, 'bronce'],
]);

test('el rango informa el avance hacia el siguiente', function () {
    $rango = app(Rangos::class)->deReputacion(150);

    expect($rango['clave'])->toBe('plata')
        ->and($rango['siguiente']['clave'])->toBe('oro')
        ->and($rango['faltan'])->toBe(150)
        ->and($rango['progreso'])->toBe(25);
});

test('el rango más alto no tiene siguiente y su progreso es completo', function () {
    $rango = app(Rangos::class)->deReputacion(5000);

    expect($rango['siguiente'])->toBeNull()->and($rango['faltan'])->toBeNull()->and($rango['progreso'])->toBe(100);
});

test('cada nivel de acceso exige el rango configurado', function () {
    $rangos = app(Rangos::class);

    expect($rangos->minimoDeNivel(NivelAcceso::Bajo))->toBe(0)
        ->and($rangos->minimoDeNivel(NivelAcceso::Medio))->toBe(100)
        ->and($rangos->minimoDeNivel(NivelAcceso::Alto))->toBe(300);
});

test('los niveles accesibles crecen con la reputación', function (int $puntos, array $niveles) {
    expect(app(Rangos::class)->nivelesAccesibles($puntos))->toBe($niveles);
})->with([
    'bronce' => [0, ['bajo']],
    'plata' => [100, ['bajo', 'medio']],
    'oro' => [300, ['bajo', 'medio', 'alto']],
    'diamante entra a todos' => [2000, ['bajo', 'medio', 'alto']],
]);

test('los umbrales se pueden cambiar desde la configuración', function () {
    config(['reputacion.rangos.plata.minimo' => 500, 'reputacion.rangos.oro.minimo' => 800]);

    expect(app(Rangos::class)->deReputacion(499)['clave'])->toBe('bronce')
        ->and(app(Rangos::class)->deReputacion(500)['clave'])->toBe('plata')
        ->and(app(Rangos::class)->nivelesAccesibles(500))->toBe(['bajo', 'medio']);
});

test('la interfaz recibe los rangos y los niveles', function () {
    $datos = app(Rangos::class)->paraInterfaz();

    expect(array_column($datos['rangos'], 'clave'))->toBe(['bronce', 'plata', 'oro', 'platino', 'diamante'])
        ->and($datos['niveles'][1])->toMatchArray(['valor' => 'medio', 'rango' => 'plata', 'rangoNombre' => 'Plata', 'minimo' => 100]);
});

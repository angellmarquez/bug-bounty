<?php

namespace App\Services\Reputacion;

use App\Enums\NivelAcceso;

/**
 * Rangos de reputación (bronce, plata, oro, platino, diamante) y niveles de acceso a programas.
 *
 * Los umbrales viven en `config/reputacion.php`: aquí solo se traducen puntos a rango y a niveles.
 */
class Rangos
{
    /**
     * Rangos ordenados de menor a mayor.
     *
     * @return array<int, array{clave: string, nombre: string, minimo: int}>
     */
    public function todos(): array
    {
        $rangos = [];

        foreach ((array) config('reputacion.rangos', []) as $clave => $datos) {
            $rangos[] = [
                'clave' => (string) $clave,
                'nombre' => (string) ($datos['nombre'] ?? ucfirst((string) $clave)),
                'minimo' => (int) ($datos['minimo'] ?? 0),
            ];
        }

        usort($rangos, fn (array $a, array $b): int => $a['minimo'] <=> $b['minimo']);

        return $rangos;
    }

    /**
     * Rango que corresponde a una cantidad de puntos, con el avance hacia el siguiente.
     *
     * @return array{clave: string, nombre: string, minimo: int, siguiente: array{clave: string, nombre: string, minimo: int}|null, faltan: int|null, progreso: int}
     */
    public function deReputacion(int $puntos): array
    {
        $todos = $this->todos();
        $actual = $todos[0];
        $siguiente = null;

        foreach ($todos as $indice => $rango) {
            if ($puntos >= $rango['minimo']) {
                $actual = $rango;
                $siguiente = $todos[$indice + 1] ?? null;
            }
        }

        $progreso = 100;

        if ($siguiente !== null) {
            $tramo = max(1, $siguiente['minimo'] - $actual['minimo']);
            $progreso = (int) max(0, min(100, floor((($puntos - $actual['minimo']) / $tramo) * 100)));
        }

        return [
            ...$actual,
            'siguiente' => $siguiente,
            'faltan' => $siguiente === null ? null : max(0, $siguiente['minimo'] - $puntos),
            'progreso' => $progreso,
        ];
    }

    /**
     * Puntos mínimos que pide un nivel de acceso.
     */
    public function minimoDeNivel(NivelAcceso|string $nivel): int
    {
        $nivel = $nivel instanceof NivelAcceso ? $nivel : NivelAcceso::from($nivel);
        $clave = $nivel->rangoRequerido();

        foreach ($this->todos() as $rango) {
            if ($rango['clave'] === $clave) {
                return $rango['minimo'];
            }
        }

        return 0;
    }

    /**
     * Niveles de acceso a los que un investigador con esos puntos puede entrar.
     *
     * @return array<int, string>
     */
    public function nivelesAccesibles(int $puntos): array
    {
        return array_values(array_map(
            fn (NivelAcceso $nivel): string => $nivel->value,
            array_filter(NivelAcceso::cases(), fn (NivelAcceso $nivel): bool => $puntos >= $this->minimoDeNivel($nivel)),
        ));
    }

    /**
     * Rangos y niveles tal como los necesita la interfaz.
     *
     * @return array{rangos: array<int, array{clave: string, nombre: string, minimo: int}>, niveles: array<int, array{valor: string, etiqueta: string, rango: string, rangoNombre: string, minimo: int}>}
     */
    public function paraInterfaz(): array
    {
        $rangos = $this->todos();
        $nombres = array_column($rangos, 'nombre', 'clave');

        return [
            'rangos' => $rangos,
            'niveles' => array_map(fn (NivelAcceso $nivel): array => [
                'valor' => $nivel->value,
                'etiqueta' => $nivel->etiqueta(),
                'rango' => $nivel->rangoRequerido(),
                'rangoNombre' => $nombres[$nivel->rangoRequerido()] ?? $nivel->rangoRequerido(),
                'minimo' => $this->minimoDeNivel($nivel),
            ], NivelAcceso::cases()),
        ];
    }
}

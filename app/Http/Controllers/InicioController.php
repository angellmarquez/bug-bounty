<?php

namespace App\Http\Controllers;

use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Reputacion\Rangos;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Página de inicio pública: qué es Huella, cifras reales y programas públicos.
 * Solo datos que ya son públicos (programas públicos y Salón de la Fama); nada cifrado.
 */
class InicioController extends Controller
{
    public function __invoke(Rangos $rangos): InertiaResponse
    {
        // Las cifras cambian poco: se cachean un momento para no consultar en cada visita.
        $datos = Cache::remember('inicio.publico', now()->addMinutes(5), function () use ($rangos): array {
            $investigadores = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('slug', 'investigador'));

            return [
                'cifras' => [
                    'programas' => Programa::query()->activos()->publicos()->count(),
                    'investigadores' => (clone $investigadores)->count(),
                    'resueltas' => Reporte::query()->whereIn('estado', Reporte::ESTADOS_APROBADOS)->count(),
                ],
                'programas' => Programa::query()
                    ->activos()
                    ->publicos()
                    ->with(['empresa:id,nombre_comercial,razon_social', 'objetivos:id,programa_id,tipo'])
                    ->latest()
                    ->limit(3)
                    ->get()
                    ->map(fn (Programa $p): array => [
                        'id' => $p->id,
                        'nombre' => $p->nombre,
                        'empresa' => $p->empresa->nombre_comercial ?? $p->empresa?->razon_social,
                        'nivel_acceso' => $p->nivel_acceso->value,
                        'tipos' => $p->objetivos->pluck('tipo')->unique()->values()->all(),
                    ])
                    ->all(),
                'lideres' => (clone $investigadores)
                    ->where('reputation_score', '>', 0)
                    ->whereDoesntHave('sanciones', fn ($q) => $q->suspensionEnCurso())
                    ->orderByDesc('reputation_score')
                    ->limit(3)
                    ->get(['id', 'name', 'reputation_score'])
                    ->map(fn (User $u): array => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'puntos' => (int) $u->reputation_score,
                        'rango' => $rangos->deReputacion((int) $u->reputation_score),
                    ])
                    ->all(),
            ];
        });

        return Inertia::render('Welcome', $datos);
    }
}

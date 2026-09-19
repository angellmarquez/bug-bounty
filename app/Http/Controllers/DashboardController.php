<?php

namespace App\Http\Controllers;

use App\Models\Programa;
use App\Models\Reporte;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request): InertiaResponse
    {
        $user = $request->user();
        $user->load('roles');

        $roles = $user->roles->pluck('slug')->toArray();
        $isAdmin = in_array('administrador', $roles);
        $isGestion = in_array('gestion', $roles);

        $query = Reporte::query();

        if (! $isAdmin && ! $isGestion) {
            $query->where('investigador_id', $user->id);
        }

        $stats = [
            'reportes_total' => $query->count(),
            'reportes_abiertos' => (clone $query)
                ->whereIn('estado', Reporte::ESTADOS_ABIERTOS)
                ->count(),
            'reportes_cerrados' => (clone $query)
                ->where('estado', 'cerrado')
                ->count(),
            'reportes_borrador' => (clone $query)
                ->where('estado', 'borrador')
                ->count(),
            'programas_activos' => $isAdmin || $isGestion
                ? Programa::where('estado', 'activo')->count()
                : Programa::where('estado', 'activo')
                    ->where('es_publico', true)
                    ->count(),
            'reputacion' => $user->reputation_score,
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'userRoles' => $roles,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\Sancion;
use App\Models\User;
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
        $isModerador = in_array('moderador', $roles);
        $isEmpresa = in_array('empresa', $roles);

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

        $roleStats = [];

        if ($isEmpresa) {
            $empresa = $user->empresas()
                ->where('empresa_usuario.estado', 'activo')
                ->latest('empresas.created_at')
                ->first();
            $programasEmpresa = $empresa?->programas() ?? Programa::query()->whereKey(0);
            $reportesEmpresa = Reporte::query()->whereIn('programa_id', $programasEmpresa->clone()->select('id'));
            $roleStats = [
                'tipo' => 'empresa',
                'estado' => $empresa?->estado->value,
                'programas_total' => $programasEmpresa->count(),
                'programas_activos' => (clone $programasEmpresa)->where('estado', 'activo')->count(),
                'miembros' => $empresa?->usuarios()->wherePivot('estado', 'activo')->count() ?? 0,
                'reportes_recibidos' => $reportesEmpresa->count(),
            ];
        } elseif ($isModerador) {
            $reportesModerador = Reporte::query()->where('estado', '!=', 'borrador');
            $roleStats = [
                'tipo' => 'moderador',
                'pendientes_revision' => (clone $reportesModerador)->whereIn('estado', ['enviado', 'en_revision'])->count(),
                'validados' => (clone $reportesModerador)->where('estado', 'validado')->count(),
                'rechazados' => (clone $reportesModerador)->where('estado', 'rechazado')->count(),
                'sanciones_aplicadas' => $user->auditorias()->where('accion', 'sancion.aplicada')->count(),
            ];
        } elseif ($isAdmin) {
            $roleStats = [
                'tipo' => 'administrador',
                'empresas_pendientes' => Empresa::where('estado', 'pendiente')->count(),
                'empresas_aprobadas' => Empresa::where('estado', 'aprobada')->count(),
                'moderadores' => User::whereHas('roles', fn ($q) => $q->where('slug', 'moderador'))->count(),
                'sanciones_activas' => Sancion::whereIn('estado', ['aplicada', 'apelada'])->count(),
            ];
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'userRoles' => $roles,
            'roleStats' => $roleStats,
        ]);
    }
}

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
        $isModerador = in_array('moderador', $roles);
        $isEmpresa = in_array('empresa', $roles);

        $query = Reporte::query();

        if (! $isAdmin) {
            $query->where('investigador_id', $user->id);
        }

        // Un solo conteo condicional en vez de 4 consultas COUNT secuenciales.
        $bindingsAbiertos = array_fill(0, count(Reporte::ESTADOS_ABIERTOS), '?');
        /** @var array<string, int|string|null> $conteos */
        $conteos = (clone $query)->selectRaw(
            'count(*) as total, '
            .'sum(case when estado in ('.implode(',', $bindingsAbiertos).') then 1 else 0 end) as abiertos, '
            .'sum(case when estado = ? then 1 else 0 end) as cerrados, '
            .'sum(case when estado = ? then 1 else 0 end) as borrador',
            [...Reporte::ESTADOS_ABIERTOS, 'cerrado', 'borrador'],
        )->first()?->toArray() ?? [];

        $stats = [
            'reportes_total' => (int) ($conteos['total'] ?? 0),
            'reportes_abiertos' => (int) ($conteos['abiertos'] ?? 0),
            'reportes_cerrados' => (int) ($conteos['cerrados'] ?? 0),
            'reportes_borrador' => (int) ($conteos['borrador'] ?? 0),
            'programas_activos' => $isAdmin
                ? Programa::where('estado', 'activo')->count()
                : Programa::where('estado', 'activo')
                    ->where('es_publico', true)
                    ->count(),
            'reputacion' => $user->reputation_score,
        ];

        $misReportes = (! $isAdmin)
            ? Reporte::where('investigador_id', $user->id)
                ->with(['programa:id,nombre', 'eventos:id,reporte_id,tipo,nota,created_at'])
                ->latest('created_at')
                ->limit(30)
                ->get(['id', 'numero_reporte', 'titulo', 'estado', 'programa_id', 'enviado_en', 'created_at'])
                ->map(function (Reporte $reporte): array {
                    $ultimo = $reporte->eventos->first();

                    return [
                        'id' => $reporte->id,
                        'numero_reporte' => $reporte->numero_reporte,
                        'titulo' => $reporte->titulo,
                        'estado' => $reporte->estado,
                        'fecha' => $reporte->enviado_en ?? $reporte->created_at,
                        'programa' => ['id' => $reporte->programa_id, 'nombre' => $reporte->programa->nombre],
                        'ultimo_evento' => $ultimo === null ? null : [
                            'tipo' => $ultimo->tipo->value,
                            'nota' => $ultimo->nota,
                            'fecha' => $ultimo->created_at?->toISOString(),
                        ],
                    ];
                })
            : [];

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
            $reportesModerador = Reporte::query()
                ->where('estado', '!=', 'borrador')
                ->whereIn('programa_id', $user->idsProgramasModerados());
            $roleStats = [
                'tipo' => 'moderador',
                'pendientes_revision' => (clone $reportesModerador)->whereIn('estado', ['enviado', 'en_revision'])->count(),
                'por_revisar' => (clone $reportesModerador)->where('estado', 'enviado')->count(),
                'validados' => (clone $reportesModerador)->where('estado', 'validado')->count(),
                'rechazados' => (clone $reportesModerador)->where('estado', 'rechazado')->count(),
                'sanciones_aplicadas' => $user->auditorias()->where('accion', 'sancion.aplicada')->count(),
            ];
        } elseif ($isAdmin) {
            // El admin no participa en el día a día de los reportes (eso es de
            // moderador/empresa): sus métricas son solo las de su función real.
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
            'misReportes' => $misReportes,
        ]);
    }
}

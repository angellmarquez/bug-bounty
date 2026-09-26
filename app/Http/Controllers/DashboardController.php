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

        // Construir la query base según el rol:
        // - Admin  → todos los reportes del sistema
        // - Empresa → reportes de sus propios programas (visible por empresa)
        // - Resto  → solo los reportes donde el usuario es el investigador
        $query = Reporte::query();

        if ($isAdmin) {
            // Admin ve todo, no filtra
        } elseif ($isEmpresa) {
            $empresa = $user->empresas()
                ->where('empresa_usuario.estado', 'activo')
                ->latest('empresas.created_at')
                ->first();
            $empresaId = $empresa ? $empresa->id : 0;
            $query->whereHas(
                'programa',
                fn ($q) => $q->where('empresa_id', $empresaId)
            )->whereIn('estado', Reporte::ESTADOS_VISIBLES_EMPRESA);
        } else {

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

        // Mis reportes recientes: solo para no-admin y no-empresa
        // (empresa ya ve sus reportes en la tarjeta roleStats['empresa'])
        $misReportes = (! $isAdmin && ! $isEmpresa)
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

        // roleStats: mapa acumulativo indexado por tipo de rol.
        // Si un usuario tiene múltiples roles (p.ej. moderador + investigador),
        // el frontend recibe las métricas de TODOS sus roles sin colisión de if/elseif.
        $roleStats = [];

        if ($isEmpresa) {
            $empresa = $user->empresas()
                ->where('empresa_usuario.estado', 'activo')
                ->latest('empresas.created_at')
                ->first();
            $programasEmpresa = $empresa?->programas() ?? Programa::query()->whereKey(0);
            // Solo cuentan los informes que la empresa puede ver (triados o resueltos), no borradores ni en revisión.
            $reportesEmpresa = Reporte::query()
                ->whereIn('programa_id', $programasEmpresa->clone()->withTrashed()->select('id'))
                ->whereIn('estado', Reporte::ESTADOS_VISIBLES_EMPRESA);
            $roleStats['empresa'] = [
                'tipo' => 'empresa',
                'estado' => $empresa?->estado->value,
                'programas_total' => $programasEmpresa->count(),
                'programas_activos' => (clone $programasEmpresa)->where('estado', 'activo')->count(),
                'reportes_recibidos' => $reportesEmpresa->count(),
            ];
        }

        if ($isModerador) {
            $reportesModerador = Reporte::query()
                ->where('estado', '!=', 'borrador')
                ->whereIn('programa_id', $user->idsProgramasModerados());
            $roleStats['moderador'] = [
                'tipo' => 'moderador',
                'pendientes_revision' => (clone $reportesModerador)->whereIn('estado', ['enviado', 'en_revision'])->count(),
                'por_revisar' => (clone $reportesModerador)->where('estado', 'enviado')->count(),
                'validados' => (clone $reportesModerador)->where('estado', 'validado')->count(),
                'rechazados' => (clone $reportesModerador)->where('estado', 'rechazado')->count(),
                'sanciones_aplicadas' => $user->auditorias()->where('accion', 'sancion.aplicada')->count(),
            ];
        }

        if ($isAdmin) {
            // El admin supervisa y arbitra: sus métricas son las de su función real.
            $roleStats['administrador'] = [
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

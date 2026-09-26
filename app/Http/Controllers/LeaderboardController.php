<?php

namespace App\Http\Controllers;

use App\Enums\Severidad;
use App\Models\EntradaReputacion;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Reputacion\Rangos;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LeaderboardController extends Controller
{
    public function __invoke(Request $request, Rangos $rangos): InertiaResponse
    {
        $periodo = (string) $request->input('periodo', 'historico');
        if (! in_array($periodo, ['historico', 'anual', 'mensual'], true)) {
            $periodo = 'historico';
        }

        $desde = match ($periodo) {
            'mensual' => now()->startOfMonth(),
            'anual' => now()->startOfYear(),
            default => null,
        };

        $baseQuery = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'investigador'))
            ->whereDoesntHave('sanciones', fn ($q) => $q->suspensionEnCurso());

        if ($desde === null) {
            $usuarios = (clone $baseQuery)
                ->where('reputation_score', '>', 0)
                ->orderByDesc('reputation_score')
                ->limit(50)
                ->get();
        } else {
            // Obtenemos los saldos del periodo agrupados directamente desde el ledger
            /** @var Collection<int, int> $saldosPeriodo */
            $saldosPeriodo = EntradaReputacion::query()
                ->where('created_at', '>=', $desde)
                ->selectRaw('usuario_id, SUM(puntos) as total_puntos')
                ->groupBy('usuario_id')
                ->havingRaw('SUM(puntos) > 0')
                ->orderByDesc('total_puntos')
                ->limit(50)
                ->pluck('total_puntos', 'usuario_id');

            $userIds = $saldosPeriodo->keys()->all();

            $usuarios = (clone $baseQuery)
                ->whereIn('id', $userIds)
                ->get()
                ->sortByDesc(fn (User $u): int => (int) $saldosPeriodo->get($u->id, 0))
                ->each(function (User $u) use ($saldosPeriodo): void {
                    $u->setAttribute('puntos_periodo', (int) $saldosPeriodo->get($u->id, 0));
                })
                ->values();
        }

        // Si no hay usuarios con puntos > 0 en histórico, mostramos hasta 10 investigadores base
        if ($usuarios->isEmpty() && $desde === null) {
            $usuarios = (clone $baseQuery)
                ->orderByDesc('reputation_score')
                ->limit(10)
                ->get();
        }

        $userIds = $usuarios->pluck('id')->all();

        // Agrupación de severidades de reportes cerrados para los top usuarios (1 query optimizada)
        $reportesPorUsuario = Reporte::query()
            ->whereIn('investigador_id', $userIds)
            ->where('estado', 'cerrado')
            ->selectRaw('investigador_id, severidad, count(*) as total')
            ->groupBy('investigador_id', 'severidad')
            ->get()
            ->groupBy('investigador_id');

        $posicion = 1;
        $ranking = $usuarios->map(function (User $user) use (&$posicion, $rangos, $periodo, $reportesPorUsuario): array {
            $puntos = (int) ($periodo === 'historico' ? $user->reputation_score : ($user->puntos_periodo ?? 0));
            $conteoSeveridades = $reportesPorUsuario->get($user->id, collect());

            $severidades = [
                'critica' => 0,
                'alta' => 0,
                'media' => 0,
                'baja' => 0,
            ];

            foreach ($conteoSeveridades as $cs) {
                $sev = $cs->severidad instanceof Severidad
                    ? $cs->severidad->value
                    : (string) $cs->severidad;

                if (isset($severidades[$sev])) {
                    $severidades[$sev] = (int) $cs->total;
                }
            }

            $totalResueltos = array_sum($severidades);

            return [
                'posicion' => $posicion++,
                'id' => $user->id,
                'name' => $user->name,
                'puntos' => $puntos,
                'rango' => $rangos->deReputacion($puntos),
                'reportes_resueltos' => $totalResueltos,
                'severidades' => $severidades,
            ];
        })->values()->all();

        $statsGlobales = [
            'total_investigadores' => User::whereHas('roles', fn ($q) => $q->where('slug', 'investigador'))->where('is_active', true)->count(),
            'total_vulnerabilidades_resueltas' => Reporte::where('estado', 'cerrado')->count(),
            'puntos_totales_repartidos' => (int) EntradaReputacion::where('puntos', '>', 0)->sum('puntos'),
        ];

        return Inertia::render('HallOfFame', [
            'periodo' => $periodo,
            'ranking' => $ranking,
            'metricas' => $statsGlobales,
        ]);
    }
}

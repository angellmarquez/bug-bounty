<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Enums\EstadoApelacion;
use App\Models\Apelacion;
use App\Models\User;
use App\Services\Reputacion\ReputationService;
use App\Services\Reputacion\TrazaApelaciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

/**
 * Apelaciones vistas desde quien las resuelve: cualquier moderador o un administrador,
 * salvo quien aplicó la sanción (juez y parte) o presentó la apelación.
 */
class ApelacionController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ApelacionResolver]);

        $query = Apelacion::query()->with(['sancion.aplicadaPor', 'sancion.reporte', 'usuario', 'resueltaPor']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        // Las pendientes primero: son las que esperan una decisión.
        $apelaciones = $query
            ->orderByRaw("case estado when 'pendiente' then 0 else 1 end")
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $apelaciones->getCollection()->transform(fn (Apelacion $apelacion) => $this->resumen($apelacion, $request->user()));

        return Inertia::render('moderacion/apelaciones/Index', [
            'apelaciones' => $apelaciones,
            'filtros' => $request->only(['estado']),
        ]);
    }

    public function show(Request $request, Apelacion $apelacion): InertiaResponse
    {
        // Se evalúa con la apelación concreta: así ABAC aplica "juez y parte" también al ver el detalle.
        Gate::authorize('abac', [AccionesAbac::ApelacionResolver, $apelacion]);

        $apelacion->load(['sancion.aplicadaPor', 'sancion.reporte', 'usuario', 'resueltaPor', 'eventos']);

        return Inertia::render('moderacion/apelaciones/Show', [
            'apelacion' => [
                ...$this->resumen($apelacion, $request->user()),
                'eventos' => $apelacion->eventos->map(fn ($evento) => [
                    'id' => $evento->id,
                    'tipo' => $evento->tipo,
                    'actor' => $evento->actor_id === null ? null : ['id' => $evento->actor_id, 'name' => $evento->actor_nombre],
                    'actor_rol' => $evento->actor_rol,
                    'ip' => $evento->ip,
                    'user_agent' => $evento->user_agent,
                    'nota' => $evento->nota,
                    'datos' => $evento->datos,
                    'huella' => $evento->huella,
                    'huella_anterior' => $evento->huella_anterior,
                    'created_at' => $evento->created_at?->toISOString(),
                ])->all(),
                'cadena_valida' => app(TrazaApelaciones::class)->verificar($apelacion),
            ],
        ]);
    }

    public function resolver(Request $request, Apelacion $apelacion, ReputationService $reputacion): RedirectResponse
    {
        // Se evalúa con la apelación concreta: así ABAC aplica "juez y parte".
        Gate::authorize('abac', [AccionesAbac::ApelacionResolver, $apelacion]);

        $validated = $request->validate([
            'aprobada' => ['required', 'boolean'],
            'nota' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $reputacion->resolverApelacion(
                $apelacion,
                (bool) $validated['aprobada'],
                $request->user(),
                $validated['nota'],
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->route('apelaciones.index')->with('error', $e->getMessage());
        }

        $texto = $validated['aprobada'] ? 'aprobada' : 'rechazada';

        return redirect()->route('apelaciones.index')
            ->with('success', "Apelación {$texto}: la decisión quedó registrada con tu nombre.");
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(Apelacion $apelacion, User $usuario): array
    {
        $sancion = $apelacion->sancion;
        $pendiente = $apelacion->estado === EstadoApelacion::Pendiente;
        $puedeResolver = $pendiente && Gate::allows('abac', [AccionesAbac::ApelacionResolver, $apelacion]);

        $bloqueo = null;
        if ($pendiente && ! $puedeResolver) {
            $bloqueo = match (true) {
                (int) $apelacion->usuario_id === (int) $usuario->id => 'Es tu propia apelación: la resuelve otra persona.',
                (int) $sancion->aplicada_por === (int) $usuario->id => 'Tú aplicaste esta sanción: la resuelve otro moderador o el administrador.',
                default => 'No tienes permiso para resolver esta apelación.',
            };
        }

        return [
            'id' => $apelacion->id,
            'estado' => $apelacion->estado->value,
            'motivo' => $apelacion->motivo,
            'nota_resolucion' => $apelacion->nota_resolucion,
            'created_at' => $apelacion->created_at?->toISOString(),
            'resuelta_en' => $apelacion->resuelta_en?->toISOString(),
            'usuario' => $apelacion->usuario->only(['id', 'name']),
            'resuelta_por' => $apelacion->resueltaPor?->only(['id', 'name']),
            'sancion' => [
                'id' => $sancion->id,
                'motivo' => $sancion->motivo,
                'gravedad' => $sancion->gravedad->value,
                'puntos' => $sancion->puntos,
                'estado' => $sancion->estado->value,
                'plazo_apelacion' => $sancion->plazo_apelacion?->toISOString(),
                'origen' => $sancion->aplicada_por === null ? 'auditoria' : 'triaje',
                'aplicada_por' => $sancion->aplicadaPor?->only(['id', 'name']),
                'reporte' => $sancion->reporte?->only(['id', 'numero_reporte', 'titulo']),
            ],
            'puede_resolver' => $puedeResolver,
            'motivo_bloqueo' => $bloqueo,
        ];
    }
}

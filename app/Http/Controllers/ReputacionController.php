<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Models\Apelacion;
use App\Models\Sancion;
use App\Services\Reputacion\ReputationService;
use App\Services\Reputacion\TrazaApelaciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

class ReputacionController extends Controller
{
    public function ledger(Request $request, ReputationService $reputacion): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReputacionVer]);

        $user = $request->user();

        $saldo = $reputacion->saldo($user);
        $historial = $reputacion->historial($user)
            ->map(fn ($entrada) => [
                'id' => $entrada->id,
                'puntos' => $entrada->puntos,
                'motivo' => $entrada->motivo,
                'metadata' => $entrada->metadata,
                'created_at' => $entrada->created_at?->toISOString(),
                'reporte' => $entrada->reporte?->only(['id', 'numero_reporte', 'titulo']),
                'sancion' => $entrada->sancion?->only(['id', 'motivo', 'gravedad']),
                'apelacion' => $entrada->apelacion?->only(['id', 'estado']),
            ]);

        return Inertia::render('Reputacion', [
            'saldo' => $saldo,
            'historial' => $historial,
        ]);
    }

    public function sanciones(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReputacionVer]);

        $user = $request->user();

        $sanciones = Sancion::query()
            ->with(['reporte', 'apelaciones'])
            ->where('usuario_id', $user->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // El servidor decide si se puede apelar (vigente, en plazo y sin apelación previa).
        $servicio = app(ReputationService::class);
        $sanciones->getCollection()->each(
            fn (Sancion $sancion) => $sancion->setAttribute('puede_apelar', $servicio->puedeApelar($sancion)),
        );

        return Inertia::render('Reputacion', [
            'seccion' => 'sanciones',
            'sanciones' => $sanciones,
            'saldo' => app(ReputationService::class)->saldo($user),
        ]);
    }

    /**
     * Seguimiento de una apelación propia: estado, quién decidió (solo su rol) y la huella de cada paso.
     */
    public function apelacion(Request $request, Apelacion $apelacion): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReputacionVer]);
        abort_unless((int) $apelacion->usuario_id === (int) $request->user()->id, 403, 'Esta apelación no es tuya.');

        $apelacion->load(['sancion.reporte', 'sancion.aplicadaPor', 'eventos']);
        $sancion = $apelacion->sancion;

        return Inertia::render('reputacion/Apelacion', [
            'apelacion' => [
                'id' => $apelacion->id,
                'estado' => $apelacion->estado->value,
                'motivo' => $apelacion->motivo,
                'nota_resolucion' => $apelacion->nota_resolucion,
                'created_at' => $apelacion->created_at?->toISOString(),
                'resuelta_en' => $apelacion->resuelta_en?->toISOString(),
                'sancion' => [
                    'id' => $sancion->id,
                    'motivo' => $sancion->motivo,
                    'gravedad' => $sancion->gravedad->value,
                    'puntos' => $sancion->puntos,
                    'estado' => $sancion->estado->value,
                    'plazo_apelacion' => $sancion->plazo_apelacion?->toISOString(),
                    // Al sancionado se le dice qué rol decidió, no el nombre de la persona.
                    'aplicada_por_rol' => $sancion->aplicadaPor === null ? 'sistema' : TrazaApelaciones::rolPrincipal($sancion->aplicadaPor),
                    'reporte' => $sancion->reporte?->only(['id', 'numero_reporte', 'titulo']),
                ],
                'eventos' => $apelacion->eventos->map(fn ($evento) => [
                    'id' => $evento->id,
                    'tipo' => $evento->tipo,
                    'actor_rol' => $evento->actor_rol,
                    'nota' => $evento->nota,
                    'huella' => $evento->huella,
                    'created_at' => $evento->created_at?->toISOString(),
                ])->all(),
                'cadena_valida' => app(TrazaApelaciones::class)->verificar($apelacion),
            ],
        ]);
    }

    public function apelaciones(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReputacionVer]);

        $user = $request->user();

        $apelaciones = Apelacion::query()
            ->with(['sancion'])
            ->where('usuario_id', $user->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Reputacion', [
            'seccion' => 'apelaciones',
            'apelaciones' => $apelaciones,
            'saldo' => app(ReputationService::class)->saldo($user),
        ]);
    }

    public function apelar(Sancion $sancion, Request $request, ReputationService $reputacion): RedirectResponse
    {
        // La regla ABAC evalúa la apelación (sus atributos `sancion.*`), no la sanción sola.
        $intento = (new Apelacion)->forceFill(['usuario_id' => $request->user()->id, 'sancion_id' => $sancion->id]);
        Gate::authorize('abac', [AccionesAbac::ApelacionCrear, $intento]);

        $request->validate([
            'motivo' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $reputacion->crearApelacion(
                $sancion,
                $request->user(),
                $request->input('motivo'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()->route('reputacion.ledger')
            ->with('success', 'Apelación presentada. Un moderador o el administrador la revisará; puedes seguir su estado en Apelaciones.');
    }
}

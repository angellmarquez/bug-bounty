<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Models\Apelacion;
use App\Models\Sancion;
use App\Services\Reputacion\ReputationService;
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
        $user = $request->user();

        $sanciones = Sancion::query()
            ->with(['reporte', 'apelaciones'])
            ->where('usuario_id', $user->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Reputacion', [
            'seccion' => 'sanciones',
            'sanciones' => $sanciones,
            'saldo' => app(ReputationService::class)->saldo($user),
        ]);
    }

    public function apelaciones(Request $request): InertiaResponse
    {
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
            ->with('success', 'Apelacion presentada exitosamente. La revision puede tardar hasta 48 horas.');
    }
}

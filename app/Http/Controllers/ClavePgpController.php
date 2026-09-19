<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Models\ClavePgp;
use App\Services\Pgp\PgpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ClavePgpController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        $claves = ClavePgp::query()
            ->where('usuario_id', $user->id)
            ->whereNull('deleted_at')
            ->orderByDesc('es_principal')
            ->orderByDesc('created_at')
            ->get();

        $pgpService = app(PgpService::class);

        return Inertia::render('ClavesPgp', [
            'claves' => $claves->map(fn ($k) => $k->only([
                'id', 'huella', 'algoritmo', 'bits', 'es_principal',
                'estado', 'creada_en', 'expira_en', 'verificada_en', 'ultimo_uso_en',
            ])),
            'pgpDisponible' => $pgpService->available(),
        ]);
    }

    public function registrar(Request $request, PgpService $pgpService): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ClavePgpRegistrar]);

        $validated = $request->validate([
            'clave_publica' => ['required', 'string', 'min:10'],
            'es_principal' => ['sometimes', 'boolean'],
        ]);

        try {
            $info = $pgpService->importPublicKey($validated['clave_publica']);
        } catch (\Throwable $e) {
            return redirect()->route('claves-pgp.index')
                ->withErrors(['clave_publica' => 'Clave publica invalida: '.$e->getMessage()]);
        }

        $user = $request->user();

        $existe = ClavePgp::query()
            ->where('usuario_id', $user->id)
            ->where('huella', $info->fingerprint)
            ->exists();

        if ($existe) {
            return redirect()->route('claves-pgp.index')
                ->withErrors(['clave_publica' => 'Esta clave ya esta registrada.']);
        }

        $esPrincipal = $validated['es_principal'] ?? false;

        if ($esPrincipal) {
            ClavePgp::query()
                ->where('usuario_id', $user->id)
                ->update(['es_principal' => false]);
        }

        ClavePgp::query()->create([
            'usuario_id' => $user->id,
            'id_clave' => $info->idClave,
            'huella' => $info->fingerprint,
            'clave_publica' => $validated['clave_publica'],
            'algoritmo' => $info->algoritmo,
            'bits' => $info->bits,
            'creada_en' => $info->creadaEn?->toDateString(),
            'expira_en' => $info->expiraEn,
            'estado' => 'pendiente_verificacion',
            'es_principal' => $esPrincipal,
        ]);

        return redirect()->route('claves-pgp.index')
            ->with('success', 'Clave PGP registrada. Pendiente de verificacion.');
    }

    public function verificar(ClavePgp $clave): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ClavePgpVerificar, $clave]);

        $clave->update([
            'estado' => 'activa',
            'verificada_en' => now(),
        ]);

        return redirect()->route('claves-pgp.index')
            ->with('success', 'Clave verificada y activada.');
    }

    public function revocar(ClavePgp $clave): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ClavePgpRevocar, $clave]);

        $clave->update([
            'estado' => 'revocada',
        ]);

        $clave->delete();

        return redirect()->route('claves-pgp.index')
            ->with('success', 'Clave revocada.');
    }
}

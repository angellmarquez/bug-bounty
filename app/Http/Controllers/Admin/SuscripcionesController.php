<?php

namespace App\Http\Controllers\Admin;

use App\Abac\AccionesAbac;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\ConfiguracionSuscripcion;
use App\Models\Empresa;
use App\Models\PagoSuscripcion;
use App\Services\Bounties\BountyBlockchainService;
use App\Services\Notificaciones\Notificador;
use App\Services\Suscripciones\PagosSuscripcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Configuración del Plan Profesional (wallet de tesorería y precio) e ingresos recibidos.
 * Cambiar la tesorería decide a quién le llega el dinero: pide la contraseña del admin,
 * queda en auditoría con la dirección anterior y avisa a todos los administradores.
 */
class SuscripcionesController extends Controller
{
    public function configuracion(BountyBlockchainService $blockchain): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ConfigSuscripcionVer]);

        $config = ConfiguracionSuscripcion::actual();

        return Inertia::render('admin/config/Plan', [
            'config' => [
                'tesoreria' => PagosSuscripcion::tesoreria(),
                'precio_usdc' => PagosSuscripcion::precio(),
                'dias' => PagosSuscripcion::dias(),
                'actualizado_en' => $config?->updated_at?->toIso8601String(),
                'actualizado_por' => $config?->editor?->name,
            ],
            'red' => $blockchain->redParaInterfaz(),
        ]);
    }

    public function actualizar(Request $request, Notificador $notificador): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ConfigSuscripcionActualizar]);

        $validated = $request->validate([
            'tesoreria' => ['nullable', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'precio_usdc' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'dias' => ['required', 'integer', 'min:1', 'max:366'],
            'password' => ['required', 'current_password'],
        ], [
            'tesoreria.regex' => 'La wallet debe ser una dirección EVM: 0x seguido de 40 caracteres hexadecimales.',
            'password.current_password' => 'La contraseña no es correcta.',
        ]);

        $anterior = PagosSuscripcion::tesoreria();
        $nueva = isset($validated['tesoreria']) && $validated['tesoreria'] !== '' ? strtolower($validated['tesoreria']) : null;

        ConfiguracionSuscripcion::query()->updateOrCreate(['id' => ConfiguracionSuscripcion::actual()->id ?? null], [
            'tesoreria' => $nueva,
            'precio_usdc' => round((float) $validated['precio_usdc'], 2),
            'dias' => (int) $validated['dias'],
            'actualizado_por' => $request->user()->id,
        ]);

        Auditoria::registrar('config.suscripcion_actualizada', null, [
            'tesoreria_anterior' => $anterior,
            'tesoreria' => $nueva,
            'precio_usdc' => round((float) $validated['precio_usdc'], 2),
            'dias' => (int) $validated['dias'],
        ], $request->user()->id);

        if ($anterior !== $nueva) {
            $notificador->tesoreriaCambiada($request->user(), $anterior, $nueva);
        }

        return redirect()->route('admin.config.plan')->with('success', 'Configuración del plan guardada.');
    }

    public function ingresos(BountyBlockchainService $blockchain): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::IngresosVer]);

        $red = $blockchain->redParaInterfaz();
        $pagos = PagoSuscripcion::query()->with('empresa:id,razon_social,nombre_comercial')->latest()->limit(100)->get();
        $confirmados = PagoSuscripcion::query()->where('estado', 'confirmado');

        return Inertia::render('admin/ingresos/Index', [
            'resumen' => [
                'total_usdc' => round((float) (clone $confirmados)->sum('monto'), 2),
                'pagos_confirmados' => (clone $confirmados)->count(),
                'ultimos_30_dias_usdc' => round((float) (clone $confirmados)->where('confirmado_en', '>=', now()->subDays(30))->sum('monto'), 2),
                'empresas_profesional' => Empresa::query()->where('plan', 'profesional')->where(fn ($q) => $q->whereNull('plan_expira_en')->orWhere('plan_expira_en', '>', now()))->count(),
                'tesoreria' => PagosSuscripcion::tesoreria(),
                'explorer_tesoreria' => $red !== null && PagosSuscripcion::tesoreria() !== null ? rtrim((string) $red['explorer_url'], '/').'/address/'.PagosSuscripcion::tesoreria() : null,
                'red' => $red['nombre'] ?? null,
            ],
            'pagos' => $pagos->map(fn (PagoSuscripcion $pago): array => [
                'id' => $pago->id,
                'empresa' => $pago->empresa->nombre_comercial ?? $pago->empresa->razon_social,
                'estado' => $pago->estado,
                'monto' => $pago->monto,
                'tx_hash' => $pago->tx_hash,
                'explorer_url' => $red !== null ? $blockchain->urlTransaccion($pago->tx_hash) : null,
                'periodo_hasta' => $pago->periodo_hasta?->toIso8601String(),
                'error' => $pago->error,
                'created_at' => $pago->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}

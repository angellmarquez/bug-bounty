<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Models\Empresa;
use App\Models\PagoSuscripcion;
use App\Services\Bounties\BountyBlockchainService;
use App\Services\Suscripciones\PagosSuscripcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * La empresa paga (o renueva) su Plan Profesional en USDC desde su wallet, directo a la
 * tesorería del proyecto, y la plataforma verifica el pago en la blockchain.
 */
class EmpresaPlanController extends Controller
{
    public function show(Request $request, BountyBlockchainService $blockchain): InertiaResponse
    {
        $empresa = $this->empresa($request);
        Gate::authorize('abac', [AccionesAbac::EmpresaPagarPlan, $empresa]);

        $precio = PagosSuscripcion::precio();
        $red = $blockchain->redParaInterfaz();

        return Inertia::render('empresa/Plan', [
            'plan' => [
                'actual' => $empresa->esPlanProfesional() ? 'profesional' : 'comunitario',
                'expira_en' => $empresa->esPlanProfesional() ? $empresa->plan_expira_en?->toIso8601String() : null,
                'precio' => $precio,
                'dias' => PagosSuscripcion::dias(),
                'monto_unidades' => $red !== null ? $blockchain->unidades($precio) : null,
                'tesoreria' => PagosSuscripcion::tesoreria(),
                'red' => $red,
            ],
            'pagos' => $empresa->pagosSuscripcion()->latest()->limit(20)->get()->map(fn (PagoSuscripcion $pago): array => [
                'id' => $pago->id,
                'estado' => $pago->estado,
                'monto' => $pago->monto,
                'dias' => $pago->dias,
                'tx_hash' => $pago->tx_hash,
                'explorer_url' => $red !== null ? $blockchain->urlTransaccion($pago->tx_hash) : null,
                'error' => $pago->error,
                'periodo_desde' => $pago->periodo_desde?->toIso8601String(),
                'periodo_hasta' => $pago->periodo_hasta?->toIso8601String(),
                'created_at' => $pago->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function pagar(Request $request, PagosSuscripcion $pagos): RedirectResponse
    {
        $empresa = $this->empresa($request);
        Gate::authorize('abac', [AccionesAbac::EmpresaPagarPlan, $empresa]);

        $validated = $request->validate([
            'tx_hash' => ['required', 'string', 'max:80'],
            'pagador' => ['nullable', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
        ]);

        $pago = $pagos->registrar($empresa, $request->user(), $validated['tx_hash'], $validated['pagador'] ?? null);

        return redirect()->route('empresa.plan')->with(
            $pago->estado === 'fallido' ? 'error' : 'success',
            match ($pago->estado) {
                'confirmado' => 'Pago verificado: tu Plan Profesional está activo.',
                'fallido' => 'La transacción no es un pago válido del plan. Revisa el detalle y registra otra.',
                default => 'Pago registrado: se está verificando en la blockchain.',
            },
        );
    }

    /** Idempotente: la página lo pide sola mientras el pago se verifica. */
    public function comprobar(Request $request, PagoSuscripcion $pago, PagosSuscripcion $pagos): RedirectResponse
    {
        $empresa = $this->empresa($request);
        Gate::authorize('abac', [AccionesAbac::EmpresaPagarPlan, $empresa]);
        abort_unless($pago->empresa_id === $empresa->id, 404);

        if ($pago->estado === 'verificando') {
            $pagos->comprobar($pago, $request->user());
        }

        return redirect()->route('empresa.plan');
    }

    private function empresa(Request $request): Empresa
    {
        return $request->user()?->empresaActiva() ?? abort(403, 'Tu usuario no pertenece a una empresa.');
    }
}

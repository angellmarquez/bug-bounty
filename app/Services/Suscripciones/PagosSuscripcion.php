<?php

namespace App\Services\Suscripciones;

use App\Models\Auditoria;
use App\Models\ConfiguracionSuscripcion;
use App\Models\Empresa;
use App\Models\PagoSuscripcion;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Bounties\BountyBlockchainService;
use App\Services\Notificaciones\Notificador;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pago del Plan Profesional en USDC a la tesorería del proyecto:
 *
 *   verificando → confirmado (extiende el plan)
 *               → fallido     (se registra otra transacción)
 *
 * La empresa paga desde su wallet; la plataforma verifica el pago en la blockchain con el
 * mismo verificador que los bounties. Nunca firma ni mueve fondos: solo conoce la
 * dirección pública de la tesorería.
 */
class PagosSuscripcion
{
    public function __construct(
        private readonly BountyBlockchainService $blockchain,
        private readonly Notificador $notificador,
    ) {}

    /** La del panel del admin; si no hay, la de config/suscripciones.php. Solo la dirección pública. */
    public static function tesoreria(): ?string
    {
        $wallet = ConfiguracionSuscripcion::actual()->tesoreria ?? config('suscripciones.tesoreria');

        return is_string($wallet) && BountyBlockchainService::esDireccionValida($wallet) ? strtolower($wallet) : null;
    }

    public static function precio(): float
    {
        return round((float) (ConfiguracionSuscripcion::actual()->precio_usdc ?? config('suscripciones.profesional.precio_usdc', 5)), 2);
    }

    public static function dias(): int
    {
        return max(1, (int) (ConfiguracionSuscripcion::actual()->dias ?? config('suscripciones.profesional.dias', 30)));
    }

    public function registrar(Empresa $empresa, User $actor, string $txHash, ?string $pagador): PagoSuscripcion
    {
        $txHash = strtolower(trim($txHash));
        $tesoreria = self::tesoreria();

        if ($tesoreria === null) {
            throw ValidationException::withMessages(['tx_hash' => 'El pago del plan no está disponible: falta configurar la wallet de tesorería del proyecto.']);
        }
        if (! BountyBlockchainService::esHashValido($txHash)) {
            throw ValidationException::withMessages(['tx_hash' => 'El hash de transacción debe empezar por 0x y tener 64 caracteres hexadecimales.']);
        }
        if ($empresa->pagosSuscripcion()->where('estado', 'verificando')->exists()) {
            throw ValidationException::withMessages(['tx_hash' => 'Ya hay un pago en verificación: espera a que termine antes de registrar otro.']);
        }
        // Una transacción solo paga una cosa: ni otro plan ni un bounty.
        $usada = PagoSuscripcion::query()->whereRaw('lower(tx_hash) = ?', [$txHash])->exists()
            || Reporte::query()->whereRaw('lower(bounty_tx_hash) = ?', [$txHash])->exists();
        if ($usada) {
            throw ValidationException::withMessages(['tx_hash' => 'Esta transacción ya se usó en otro pago de la plataforma.']);
        }

        $pago = $empresa->pagosSuscripcion()->create([
            'usuario_id' => $actor->id,
            'plan' => 'profesional',
            'monto' => self::precio(),
            'dias' => self::dias(),
            'red' => $this->blockchain->red()['clave'],
            'tx_hash' => $txHash,
            'wallet_destino' => $tesoreria,
            'pagador' => $pagador !== null && BountyBlockchainService::esDireccionValida($pagador) ? strtolower($pagador) : null,
            'estado' => 'verificando',
        ]);

        Auditoria::registrar('empresas.plan_pago_registrado', $empresa, ['tx_hash' => $txHash, 'monto' => $pago->monto, 'pago_id' => $pago->id], $actor->id);

        $this->comprobar($pago, $actor);

        return $pago->fresh() ?? $pago;
    }

    /**
     * @return string confirmado, pendiente o fallido
     */
    public function comprobar(PagoSuscripcion $pago, ?User $actor = null): string
    {
        return DB::transaction(function () use ($pago, $actor): string {
            /** @var PagoSuscripcion $pago */
            $pago = PagoSuscripcion::query()->whereKey($pago->id)->lockForUpdate()->firstOrFail();

            if ($pago->estado !== 'verificando') {
                return $pago->estado === 'confirmado' ? BountyBlockchainService::CONFIRMADO : BountyBlockchainService::FALLIDO;
            }

            $verificacion = $this->blockchain->verificar($pago->tx_hash, $pago->wallet_destino, $pago->monto);

            if ($verificacion['resultado'] === BountyBlockchainService::PENDIENTE
                && $pago->created_at?->lt(now()->subMinutes((int) config('bounty.verificacion_expira_minutos', 30)))) {
                $verificacion = [
                    'resultado' => BountyBlockchainService::FALLIDO,
                    'motivo' => 'La transacción no se confirmó a tiempo. Revisa que se haya enviado en la red correcta y registra otra.',
                ];
            }

            match ($verificacion['resultado']) {
                BountyBlockchainService::CONFIRMADO => $this->confirmar($pago, $verificacion, $actor),
                BountyBlockchainService::FALLIDO => $this->fallar($pago, $verificacion['motivo'], $actor),
                default => $pago->update(['error' => $verificacion['motivo'], 'bloque' => $verificacion['bloque'] ?? $pago->bloque]),
            };

            return $verificacion['resultado'];
        });
    }

    /**
     * @param  array{resultado: string, motivo: string, bloque?: int, pagador?: string}  $verificacion
     */
    private function confirmar(PagoSuscripcion $pago, array $verificacion, ?User $actor): void
    {
        $empresa = $pago->empresa;

        // Renovar antes de vencer suma los días al vencimiento actual: no se pierde lo ya pagado.
        $desde = $empresa->esPlanProfesional() && $empresa->plan_expira_en !== null && $empresa->plan_expira_en->isFuture()
            ? Carbon::parse($empresa->plan_expira_en)
            : now();
        $hasta = $desde->copy()->addDays($pago->dias);

        $empresa->update([
            'plan' => 'profesional',
            'plan_expira_en' => $hasta,
            'plan_aviso_vencimiento_en' => null,
        ]);

        $pago->update([
            'estado' => 'confirmado',
            'error' => null,
            'bloque' => $verificacion['bloque'] ?? null,
            'pagador' => $verificacion['pagador'] ?? $pago->pagador,
            'periodo_desde' => $desde,
            'periodo_hasta' => $hasta,
            'confirmado_en' => now(),
        ]);

        Auditoria::registrar('empresas.plan_pagado', $empresa, [
            'pago_id' => $pago->id,
            'tx_hash' => $pago->tx_hash,
            'monto' => $pago->monto,
            'hasta' => $hasta->toDateString(),
        ], $actor?->id);
        $this->notificador->planPagado($empresa, $pago);
    }

    private function fallar(PagoSuscripcion $pago, string $motivo, ?User $actor): void
    {
        $pago->update(['estado' => 'fallido', 'error' => $motivo]);

        Auditoria::registrar('empresas.plan_pago_fallido', $pago->empresa, ['pago_id' => $pago->id, 'tx_hash' => $pago->tx_hash, 'motivo' => $motivo], $actor?->id);
        $this->notificador->planPagoFallido($pago->empresa, $motivo);
    }
}

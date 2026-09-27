<?php

namespace App\Services\Bounties;

use App\Enums\TipoEventoReporte;
use App\Models\Auditoria;
use App\Models\PagoSuscripcion;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Notificaciones\Notificador;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de vida del pago de un bounty:
 *
 *   sin_bounty → asignado → verificando → pagado
 *                    ↑           │
 *                    └─ fallido ←┘   (se puede reintentar con otra transacción)
 *
 * La empresa asigna el monto, paga desde su wallet y registra la transacción; la
 * plataforma la verifica en la blockchain. Un bounty pagado ya no se modifica.
 */
class PagosBounty
{
    public function __construct(
        private readonly BountyBlockchainService $blockchain,
        private readonly Notificador $notificador,
    ) {}

    public function asignar(Reporte $reporte, float $monto, User $actor): void
    {
        $this->exigirEstado($reporte, ['sin_bounty', 'asignado', 'fallido'], 'monto', 'El monto ya no se puede cambiar: el pago está en verificación o ya se hizo.');

        $programa = $reporte->programa;
        if ($programa->recompensa_min !== null && $monto < $programa->recompensa_min) {
            throw ValidationException::withMessages(['monto' => "El programa paga como mínimo {$programa->recompensa_min} USDC."]);
        }
        if ($programa->recompensa_max !== null && $monto > $programa->recompensa_max) {
            throw ValidationException::withMessages(['monto' => "El programa paga como máximo {$programa->recompensa_max} USDC."]);
        }

        $red = $this->blockchain->red();

        $reporte->update([
            'bounty_monto' => $monto,
            'bounty_moneda' => 'USDC',
            'bounty_red' => $red['clave'],
            'bounty_estado' => 'asignado',
            'bounty_error' => null,
        ]);

        $this->registrarEvento($reporte, $actor, "Recompensa de {$this->formato($monto)} USDC asignada al informe.", [
            'bounty_monto' => $monto,
            'bounty_red' => $red['clave'],
        ]);
        Auditoria::registrar('reportes.bounty_asignado', $reporte, ['monto' => $monto, 'red' => $red['clave']], $actor->id);
        $this->notificador->bountyAsignado($reporte, $actor);
    }

    /**
     * Registra la transacción con la que la empresa pagó y la verifica al momento.
     * La wallet de destino queda fijada: es la que se verificará aunque el investigador la cambie después.
     */
    public function registrarTransaccion(Reporte $reporte, string $txHash, ?string $pagador, User $actor): void
    {
        $txHash = strtolower(trim($txHash));

        if (! BountyBlockchainService::esHashValido($txHash)) {
            throw ValidationException::withMessages(['tx_hash' => 'El hash de transacción debe empezar por 0x y tener 64 caracteres hexadecimales.']);
        }

        $this->exigirEstado($reporte, ['asignado', 'fallido'], 'tx_hash', 'Este bounty no admite un pago ahora: asigna primero el monto o espera a que termine la verificación en curso.');

        $wallet = $reporte->investigador->wallet_address;
        if ($wallet === null || ! BountyBlockchainService::esDireccionValida($wallet)) {
            throw ValidationException::withMessages(['tx_hash' => 'El investigador aún no configuró una wallet válida en su perfil para recibir la recompensa.']);
        }

        if (Reporte::query()->whereRaw('lower(bounty_tx_hash) = ?', [$txHash])->whereKeyNot($reporte->id)->exists()) {
            throw ValidationException::withMessages(['tx_hash' => 'Esta transacción ya se usó para pagar otro informe.']);
        }
        if (PagoSuscripcion::query()->whereRaw('lower(tx_hash) = ?', [$txHash])->exists()) {
            throw ValidationException::withMessages(['tx_hash' => 'Esta transacción ya se usó para pagar un plan de empresa.']);
        }

        $reporte->update([
            'bounty_tx_hash' => $txHash,
            'bounty_red' => $this->blockchain->red()['clave'],
            'bounty_wallet_destino' => strtolower($wallet),
            'bounty_pagador' => $pagador !== null && BountyBlockchainService::esDireccionValida($pagador) ? strtolower($pagador) : null,
            'bounty_bloque' => null,
            'bounty_tx_registrada_en' => now(),
            'bounty_estado' => 'verificando',
            'bounty_error' => null,
        ]);

        $this->registrarEvento($reporte, $actor, 'Pago del bounty enviado: verificando la transacción en la blockchain.', [
            'bounty_tx_hash' => $txHash,
            'wallet_destino' => strtolower($wallet),
            'explorer_url' => $this->blockchain->urlTransaccion($txHash),
        ]);
        Auditoria::registrar('reportes.bounty_transaccion_registrada', $reporte, ['tx_hash' => $txHash, 'wallet_destino' => strtolower($wallet)], $actor->id);

        $this->comprobar($reporte, $actor);
    }

    /**
     * Vuelve a consultar la blockchain para un pago en verificación.
     *
     * @return string el resultado: confirmado, pendiente o fallido.
     */
    public function comprobar(Reporte $reporte, ?User $actor = null): string
    {
        return DB::transaction(function () use ($reporte, $actor): string {
            /** @var Reporte $reporte */
            $reporte = Reporte::query()->whereKey($reporte->id)->lockForUpdate()->firstOrFail();

            if ($reporte->bounty_estado !== 'verificando' || $reporte->bounty_tx_hash === null || $reporte->bounty_wallet_destino === null) {
                return $reporte->bounty_estado === 'pagado' ? BountyBlockchainService::CONFIRMADO : BountyBlockchainService::PENDIENTE;
            }

            $verificacion = $this->blockchain->verificar($reporte->bounty_tx_hash, $reporte->bounty_wallet_destino, (float) $reporte->bounty_monto);

            if ($verificacion['resultado'] === BountyBlockchainService::PENDIENTE && $this->expiro($reporte)) {
                $verificacion = [
                    'resultado' => BountyBlockchainService::FALLIDO,
                    'motivo' => 'La transacción no se confirmó en '.config('bounty.verificacion_expira_minutos').' minutos. Revisa que se haya enviado en la red correcta y registra otra.',
                ];
            }

            match ($verificacion['resultado']) {
                BountyBlockchainService::CONFIRMADO => $this->confirmar($reporte, $verificacion, $actor),
                BountyBlockchainService::FALLIDO => $this->fallar($reporte, $verificacion['motivo'], $actor),
                default => $reporte->update([
                    'bounty_error' => $verificacion['motivo'],
                    'bounty_bloque' => $verificacion['bloque'] ?? $reporte->bounty_bloque,
                ]),
            };

            return $verificacion['resultado'];
        });
    }

    /**
     * @param  array{resultado: string, motivo: string, bloque?: int, pagador?: string}  $verificacion
     */
    private function confirmar(Reporte $reporte, array $verificacion, ?User $actor): void
    {
        $reporte->update([
            'bounty_estado' => 'pagado',
            'bounty_pagado_en' => now(),
            'bounty_bloque' => $verificacion['bloque'] ?? null,
            'bounty_pagador' => $verificacion['pagador'] ?? $reporte->bounty_pagador,
            'bounty_error' => null,
        ]);

        $this->registrarEvento($reporte, $actor, "Bounty de {$this->formato((float) $reporte->bounty_monto)} USDC pagado y verificado en la blockchain.", [
            'bounty_tx_hash' => $reporte->bounty_tx_hash,
            'bounty_bloque' => $verificacion['bloque'] ?? null,
            'bounty_red' => $reporte->bounty_red,
            'explorer_url' => $this->blockchain->urlTransaccion((string) $reporte->bounty_tx_hash),
        ]);
        Auditoria::registrar('reportes.bounty_pagado', $reporte, [
            'tx_hash' => $reporte->bounty_tx_hash,
            'bloque' => $verificacion['bloque'] ?? null,
            'monto' => (float) $reporte->bounty_monto,
            'wallet_destino' => $reporte->bounty_wallet_destino,
        ], $actor?->id);
        $this->notificador->bountyPagado($reporte);
    }

    private function fallar(Reporte $reporte, string $motivo, ?User $actor): void
    {
        $reporte->update(['bounty_estado' => 'fallido', 'bounty_error' => $motivo]);

        $this->registrarEvento($reporte, $actor, "La verificación del pago falló: {$motivo}", [
            'bounty_tx_hash' => $reporte->bounty_tx_hash,
        ]);
        Auditoria::registrar('reportes.bounty_verificacion_fallida', $reporte, ['tx_hash' => $reporte->bounty_tx_hash, 'motivo' => $motivo], $actor?->id);
        $this->notificador->bountyFallido($reporte, $motivo);
    }

    private function expiro(Reporte $reporte): bool
    {
        return $reporte->bounty_tx_registrada_en !== null
            && $reporte->bounty_tx_registrada_en->lt(now()->subMinutes((int) config('bounty.verificacion_expira_minutos', 30)));
    }

    /**
     * @param  array<int, string>  $permitidos
     */
    private function exigirEstado(Reporte $reporte, array $permitidos, string $campo, string $mensaje): void
    {
        if (! in_array($reporte->bounty_estado ?? 'sin_bounty', $permitidos, true)) {
            throw ValidationException::withMessages([$campo => $mensaje]);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function registrarEvento(Reporte $reporte, ?User $actor, string $nota, array $datos): void
    {
        $reporte->eventos()->create([
            'actor_id' => $actor?->id,
            'tipo' => TipoEventoReporte::Bounty,
            'nota' => $nota,
            'datos' => ['bounty' => true, ...$datos],
        ]);
    }

    private function formato(float $monto): string
    {
        return number_format($monto, 2, '.', '');
    }
}

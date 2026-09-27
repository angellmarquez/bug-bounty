<?php

namespace App\Services\Bounties;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Verifica en la blockchain el pago de un bounty en USDC, leyendo el recibo de la
 * transacción por JSON-RPC. No firma ni envía nada: la plataforma nunca custodia fondos.
 *
 * Un pago solo se confirma si el recibo prueba que el contrato USDC oficial de la red
 * transfirió al menos el monto asignado a la wallet del investigador y la transacción ya
 * tiene las confirmaciones exigidas. Si la red no responde, el pago queda pendiente: una
 * caída del proveedor RPC nunca puede dar un pago por bueno.
 */
class BountyBlockchainService
{
    public const CONFIRMADO = 'confirmado';

    public const PENDIENTE = 'pendiente';

    public const FALLIDO = 'fallido';

    /**
     * Clave y configuración de la red activa.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException si la red no existe o es mainnet sin habilitar.
     */
    public function red(): array
    {
        $clave = (string) config('bounty.red');
        $red = config("bounty.redes.{$clave}");

        if (! is_array($red)) {
            throw new RuntimeException("La red de pagos «{$clave}» no está configurada.");
        }

        if (! $red['testnet'] && ! config('bounty.permitir_mainnet')) {
            throw new RuntimeException('Los pagos en mainnet (dinero real) están deshabilitados. Actívalos con BOUNTY_PERMITIR_MAINNET=true solo cuando estén probados en testnet.');
        }

        return [...$red, 'clave' => $clave];
    }

    /**
     * La red activa con lo que la wallet del navegador necesita para cambiarse a ella y pagar;
     * null si no está disponible (red desconocida o mainnet deshabilitada).
     *
     * @return array<string, mixed>|null
     */
    public function redParaInterfaz(): ?array
    {
        try {
            $red = $this->red();
        } catch (RuntimeException) {
            return null;
        }

        return [
            'clave' => $red['clave'],
            'nombre' => $red['nombre'],
            'testnet' => $red['testnet'],
            'chain_id' => $red['chain_id'],
            'rpc_url' => $red['rpc_url'],
            'explorer_url' => $red['explorer_url'],
            'moneda_nativa' => $red['moneda_nativa'],
            'usdc' => $red['usdc'],
            'confirmaciones' => $red['confirmaciones'],
            'propina_minima_gwei' => $red['propina_minima_gwei'] ?? 0,
            'faucets' => $red['faucets'],
        ];
    }

    /**
     * Monto en unidades mínimas del token (USDC tiene 6 decimales), como entero en texto.
     */
    public function unidades(float $monto): string
    {
        $centavos = (int) round($monto * 100);

        return (string) ($centavos * (10 ** ((int) $this->red()['decimales'] - 2)));
    }

    public function urlTransaccion(string $txHash): string
    {
        return rtrim((string) $this->red()['explorer_url'], '/').'/tx/'.$txHash;
    }

    public static function esHashValido(string $txHash): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash) === 1;
    }

    public static function esDireccionValida(string $direccion): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $direccion) === 1;
    }

    /**
     * Comprueba el pago de `$monto` USDC a `$walletDestino` hecho en la transacción `$txHash`.
     *
     * @return array{resultado: string, motivo: string, confirmaciones?: int, requeridas?: int, bloque?: int, pagador?: string}
     */
    public function verificar(string $txHash, string $walletDestino, float $monto): array
    {
        $red = $this->red();

        try {
            $recibo = $this->rpc('eth_getTransactionReceipt', [$txHash]);
        } catch (Throwable $e) {
            report($e);

            return ['resultado' => self::PENDIENTE, 'motivo' => 'No se pudo consultar la red en este momento; se volverá a intentar.'];
        }

        if ($recibo === null) {
            return ['resultado' => self::PENDIENTE, 'motivo' => 'La transacción todavía no aparece en la red.'];
        }

        if (! is_array($recibo)) {
            return ['resultado' => self::PENDIENTE, 'motivo' => 'La red devolvió una respuesta inesperada; se volverá a intentar.'];
        }

        if (($recibo['status'] ?? null) !== '0x1') {
            return ['resultado' => self::FALLIDO, 'motivo' => 'La transacción falló o fue revertida en la blockchain.'];
        }

        $esperado = $this->hexDeEntero($this->unidades($monto));
        $mayorRecibido = null;

        foreach ((array) ($recibo['logs'] ?? []) as $log) {
            if (! $this->esTransferUsdcA($log, (string) $red['usdc'], $walletDestino)) {
                continue;
            }

            $valor = $this->normalizarHex((string) ($log['data'] ?? '0x0'));
            if ($mayorRecibido === null || $this->compararHex($valor, $mayorRecibido) > 0) {
                $mayorRecibido = $valor;
            }
        }

        if ($mayorRecibido === null) {
            return ['resultado' => self::FALLIDO, 'motivo' => "La transacción no transfiere USDC de {$red['nombre']} a la wallet del investigador."];
        }

        if ($this->compararHex($mayorRecibido, $esperado) < 0) {
            $recibido = hexdec($mayorRecibido) / (10 ** (int) $red['decimales']);

            return ['resultado' => self::FALLIDO, 'motivo' => sprintf('Monto insuficiente: la transacción envía %s USDC y el bounty es de %s USDC.', number_format($recibido, 2, '.', ''), number_format($monto, 2, '.', ''))];
        }

        $bloque = (int) hexdec($this->normalizarHex((string) ($recibo['blockNumber'] ?? '0x0')));
        $requeridas = (int) $red['confirmaciones'];

        try {
            $ultimo = (int) hexdec($this->normalizarHex((string) $this->rpc('eth_blockNumber', [])));
        } catch (Throwable $e) {
            report($e);

            return ['resultado' => self::PENDIENTE, 'motivo' => 'No se pudo consultar el último bloque; se volverá a intentar.'];
        }

        $confirmaciones = max(0, $ultimo - $bloque + 1);
        $pagador = strtolower((string) ($recibo['from'] ?? ''));

        if ($confirmaciones < $requeridas) {
            return [
                'resultado' => self::PENDIENTE,
                'motivo' => "Pago encontrado: esperando confirmaciones ({$confirmaciones} de {$requeridas}).",
                'confirmaciones' => $confirmaciones,
                'requeridas' => $requeridas,
                'bloque' => $bloque,
                'pagador' => $pagador,
            ];
        }

        return [
            'resultado' => self::CONFIRMADO,
            'motivo' => "Pago confirmado en el bloque {$bloque} ({$confirmaciones} confirmaciones).",
            'confirmaciones' => $confirmaciones,
            'requeridas' => $requeridas,
            'bloque' => $bloque,
            'pagador' => $pagador,
        ];
    }

    /**
     * @param  array<int, mixed>  $params
     *
     * @throws RuntimeException si el proveedor RPC falla o responde con error.
     */
    protected function rpc(string $metodo, array $params): mixed
    {
        $respuesta = Http::timeout((int) config('bounty.rpc_timeout_segundos', 10))
            ->acceptJson()
            ->post((string) $this->red()['rpc_url'], [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => $metodo,
                'params' => $params,
            ]);

        if (! $respuesta->successful()) {
            throw new RuntimeException("El proveedor RPC respondió HTTP {$respuesta->status()} a {$metodo}.");
        }

        $json = $respuesta->json();

        if (! is_array($json) || isset($json['error']) || ! array_key_exists('result', $json)) {
            throw new RuntimeException("El proveedor RPC devolvió un error a {$metodo}.");
        }

        return $json['result'];
    }

    /**
     * ¿Es este log un Transfer del contrato USDC hacia `$destino`?
     */
    private function esTransferUsdcA(mixed $log, string $usdc, string $destino): bool
    {
        if (! is_array($log)) {
            return false;
        }

        $topics = array_map(fn ($t): string => strtolower((string) $t), (array) ($log['topics'] ?? []));

        return strtolower((string) ($log['address'] ?? '')) === strtolower($usdc)
            && ($topics[0] ?? null) === strtolower((string) config('bounty.topic_transfer'))
            && isset($topics[2])
            && '0x'.substr($topics[2], -40) === strtolower($destino);
    }

    /** Hex sin prefijo ni ceros a la izquierda, en minúsculas ("0" si es cero). */
    private function normalizarHex(string $hex): string
    {
        $hex = strtolower(preg_replace('/^0x/i', '', trim($hex)) ?? '');
        $hex = ltrim($hex, '0');

        return $hex === '' ? '0' : $hex;
    }

    /** Compara dos hex normalizados sin límite de tamaño (los montos de un token son uint256). */
    private function compararHex(string $a, string $b): int
    {
        return strlen($a) <=> strlen($b) ?: strcmp($a, $b);
    }

    private function hexDeEntero(string $entero): string
    {
        return $this->normalizarHex(dechex((int) $entero));
    }
}

<?php

namespace App\Services\Bounties;

use App\Models\Reporte;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Servicio encargado de verificar transacciones blockchain para la liquidación
 * de bounties en stablecoins (USDC / USDT) con soporte para driver simulado y real.
 */
class BountyBlockchainService
{
    /**
     * Valida y procesa el hash de transacción para un reporte y su investigador.
     *
     * @return array{
     *     valido: bool,
     *     tx_hash: string,
     *     red: string,
     *     monto: float,
     *     destinatario: string,
     *     explorer_url: string,
     *     simulado: bool,
     * }
     *
     * @throws InvalidArgumentException
     */
    public function verificarYProcesar(
        Reporte $reporte,
        string $txHash,
        float $monto,
        string $walletDestino,
        string $red = 'Polygon'
    ): array {
        $txHash = trim($txHash);

        // 1. Validación sintáctica de hash EVM (0x seguido de 64 caracteres hexadecimales)
        if (! preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
            throw new InvalidArgumentException('El hash de transacción no tiene un formato válido (debe iniciar con 0x y tener 64 caracteres hexadecimales).');
        }

        // 2. Anti-Replay: el hash no puede haberse usado en otro reporte
        $existe = Reporte::query()
            ->where('bounty_tx_hash', $txHash)
            ->whereKeyNot($reporte->id)
            ->exists();

        if ($existe) {
            throw new InvalidArgumentException('Este hash de transacción ya ha sido registrado en otro reporte previo.');
        }

        $driver = config('bounty.driver', 'simulado');

        if ($driver === 'simulado') {
            return [
                'valido' => true,
                'tx_hash' => $txHash,
                'red' => $red,
                'monto' => $monto,
                'destinatario' => $walletDestino,
                'explorer_url' => $this->generarExplorerUrl($red, $txHash),
                'simulado' => true,
            ];
        }

        // Driver real (Polygonscan / Etherscan API)
        return $this->verificarEnExplorador($txHash, $monto, $walletDestino, $red);
    }

    /**
     * Consulta la API del explorador de la red para comprobar el estado de la transacción.
     *
     * @return array{
     *     valido: bool,
     *     tx_hash: string,
     *     red: string,
     *     monto: float,
     *     destinatario: string,
     *     explorer_url: string,
     *     simulado: bool,
     * }
     */
    protected function verificarEnExplorador(string $txHash, float $monto, string $walletDestino, string $red): array
    {
        $redes = config('bounty.redes', []);
        $configRed = $redes[$red] ?? $redes['Polygon'] ?? null;

        if ($configRed === null || empty($configRed['api_url'])) {
            throw new InvalidArgumentException("La red \"{$red}\" no tiene configurada una API de verificación.");
        }

        $apiKey = (string) config('bounty.api_key', '');
        $url = $configRed['api_url'];

        try {
            $response = Http::timeout(10)->get($url, [
                'module' => 'transaction',
                'action' => 'gettxreceiptstatus',
                'txhash' => $txHash,
                'apikey' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['result']['status'] ?? null;

                if ($status !== '1') {
                    throw new InvalidArgumentException('La transacción en blockchain no tuvo éxito o fue revertida.');
                }
            }
        } catch (\Throwable $e) {
            if ($e instanceof InvalidArgumentException) {
                throw $e;
            }
            // En caso de indisponibilidad temporal de la API externa
            report($e);
            throw new InvalidArgumentException('No se pudo conectar con el explorador de bloques para verificar la transacción. Intenta de nuevo en unos momentos.');
        }

        return [
            'valido' => true,
            'tx_hash' => $txHash,
            'red' => $red,
            'monto' => $monto,
            'destinatario' => $walletDestino,
            'explorer_url' => $this->generarExplorerUrl($red, $txHash),
            'simulado' => false,
        ];
    }

    /**
     * Genera la URL pública para ver la transacción en el explorador de bloques.
     */
    public function generarExplorerUrl(string $red, string $txHash): string
    {
        $redes = config('bounty.redes', []);
        $baseUrl = $redes[$red]['explorer_tx_url'] ?? 'https://polygonscan.com/tx/';

        return rtrim($baseUrl, '/').'/'.$txHash;
    }
}

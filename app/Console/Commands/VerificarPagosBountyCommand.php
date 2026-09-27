<?php

namespace App\Console\Commands;

use App\Models\Reporte;
use App\Services\Bounties\PagosBounty;
use Illuminate\Console\Command;

/**
 * Vuelve a consultar la blockchain para los pagos de bounties aún en verificación. La
 * página del informe lo hace sola mientras está abierta; esto cubre el resto (corre cada
 * minuto con el scheduler) y cierra por expiración los que nunca aparecen en la red.
 */
class VerificarPagosBountyCommand extends Command
{
    protected $signature = 'bounties:verificar-pendientes';

    protected $description = 'Verifica en la blockchain los pagos de bounties pendientes de confirmación';

    public function handle(PagosBounty $pagos): int
    {
        $pendientes = Reporte::query()->where('bounty_estado', 'verificando')->orderBy('bounty_tx_registrada_en')->limit(50)->get();

        foreach ($pendientes as $reporte) {
            $this->line("{$reporte->numero_reporte}: {$pagos->comprobar($reporte)}");
        }

        $this->info("Revisados {$pendientes->count()} pagos en verificación.");

        return self::SUCCESS;
    }
}

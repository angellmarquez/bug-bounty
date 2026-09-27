<?php

namespace App\Console\Commands;

use App\Models\PagoSuscripcion;
use App\Services\Suscripciones\PagosSuscripcion;
use Illuminate\Console\Command;

/**
 * Vuelve a consultar la blockchain para los pagos del plan aún en verificación.
 */
class VerificarPagosSuscripcionCommand extends Command
{
    protected $signature = 'suscripciones:verificar-pendientes';

    protected $description = 'Verifica en la blockchain los pagos del Plan Profesional pendientes de confirmación';

    public function handle(PagosSuscripcion $pagos): int
    {
        $pendientes = PagoSuscripcion::query()->where('estado', 'verificando')->oldest()->limit(50)->get();

        foreach ($pendientes as $pago) {
            $this->line("Pago {$pago->id}: {$pagos->comprobar($pago)}");
        }

        $this->info("Revisados {$pendientes->count()} pagos de plan en verificación.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Models\Empresa;
use App\Services\Notificaciones\Notificador;
use Illuminate\Console\Command;

/**
 * Avisa a las empresas cuyo Plan Profesional vence pronto (una sola vez por periodo) y pasa
 * a Comunitario las que ya vencieron. Sus programas siguen funcionando: solo pierden la
 * posibilidad de crear nuevos privados, de élite o solo para verificados.
 */
class RevisarVencimientoPlanesCommand extends Command
{
    protected $signature = 'suscripciones:revisar-vencimientos';

    protected $description = 'Avisa de los planes que vencen pronto y degrada los vencidos a Comunitario';

    public function handle(Notificador $notificador): int
    {
        $porVencer = Empresa::query()
            ->where('plan', 'profesional')
            ->whereNull('plan_aviso_vencimiento_en')
            ->whereBetween('plan_expira_en', [now(), now()->addDays((int) config('suscripciones.aviso_dias_antes', 5))])
            ->get();

        foreach ($porVencer as $empresa) {
            $empresa->update(['plan_aviso_vencimiento_en' => now()]);
            $notificador->planPorVencer($empresa);
        }

        $vencidas = Empresa::query()
            ->where('plan', 'profesional')
            ->whereNotNull('plan_expira_en')
            ->where('plan_expira_en', '<', now())
            ->get();

        foreach ($vencidas as $empresa) {
            $vencio = $empresa->plan_expira_en?->toDateString();
            $empresa->update(['plan' => 'comunitario', 'plan_aviso_vencimiento_en' => null]);
            Auditoria::registrar('empresas.plan_vencido', $empresa, ['vencio_en' => $vencio], usuarioId: null);
            $notificador->planVencido($empresa);
        }

        $this->info("Avisos de vencimiento: {$porVencer->count()} · planes vencidos: {$vencidas->count()}.");

        return self::SUCCESS;
    }
}

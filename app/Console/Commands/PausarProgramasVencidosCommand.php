<?php

namespace App\Console\Commands;

use App\Enums\EstadoPrograma;
use App\Models\Auditoria;
use App\Models\Programa;
use App\Services\Moderacion\AutoAsignadorModeradores;
use App\Services\Notificaciones\Notificador;
use Illuminate\Console\Command;

/**
 * Pone en pausa los programas activos cuya fecha de fin ya pasó. Desde ese día ya no
 * aceptaban informes (lo impide ABAC); esto deja el estado a juego y avisa a la empresa,
 * que puede ampliar la fecha y reactivarlo o darlo por resuelto. Los informes que ya están
 * en triaje siguen su curso.
 */
class PausarProgramasVencidosCommand extends Command
{
    protected $signature = 'programas:pausar-vencidos';

    protected $description = 'Pone en pausa los programas activos cuya fecha de fin ya pasó';

    public function handle(Notificador $notificador, AutoAsignadorModeradores $asignador): int
    {
        $vencidos = Programa::query()
            ->where('estado', EstadoPrograma::Activo)
            ->whereNotNull('termina_en')
            ->where('termina_en', '<', today())
            ->get()
            ->filter(fn (Programa $programa): bool => $programa->haTerminado());

        foreach ($vencidos as $programa) {
            $programa->update(['estado' => EstadoPrograma::EnPausa]);

            Auditoria::registrar('programas.pausado_por_fecha', $programa, [
                'termina_en' => $programa->termina_en?->toDateString(),
            ], usuarioId: null);
            $notificador->programaVencido($programa);

            $this->line("Pausado: {$programa->nombre} (terminó el {$programa->termina_en?->toDateString()})");
        }

        if ($vencidos->isNotEmpty()) {
            $asignador->atenderProgramasPendientes();
        }

        $this->info("Programas pausados por fecha: {$vencidos->count()}.");

        return self::SUCCESS;
    }
}

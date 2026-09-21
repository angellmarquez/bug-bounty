<?php

namespace App\Observers;

use App\Models\EventoReporte;
use App\Services\Notificaciones\Notificador;

/**
 * Cada cambio en la línea de tiempo de un informe (envío, cambio de estado, asignación,
 * comentario, duplicado) genera sus avisos en un solo lugar.
 */
class EventoReporteObserver
{
    public function created(EventoReporte $evento): void
    {
        app(Notificador::class)->desdeEventoDeInforme($evento);
    }
}

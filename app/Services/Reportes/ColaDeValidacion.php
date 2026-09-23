<?php

namespace App\Services\Reportes;

use App\Models\Reporte;
use Carbon\CarbonInterface;

/**
 * Orden de llegada de los informes de un programa: la recompensa es para quien encontró
 * la vulnerabilidad primero, así que se validan y se confirman por orden.
 *
 *  - Prioridad = primer envío (`enviado_en`, que no cambia al reenviar tras `needs_info`).
 *    Un informe sin fecha de envío (datos anteriores a este campo) cae en su `created_at`.
 *  - El moderador no valida un informe mientras haya uno anterior del programa sin triar.
 *  - La empresa no confirma (en reparación o cerrado desde validado, que es cuando se
 *    otorgan los puntos) mientras haya uno anterior sin triar o validado sin confirmar.
 *  - Rechazar o marcar duplicado no otorga puntos: se puede hacer en cualquier orden.
 */
class ColaDeValidacion
{
    /** Estados en los que un informe todavía puede resultar válido. */
    public const PENDIENTES_DE_TRIAJE = ['enviado', 'en_revision', 'needs_info'];

    /** Además de los anteriores, los validados que la empresa aún no confirmó. */
    public const PENDIENTES_DE_CONFIRMAR = [...self::PENDIENTES_DE_TRIAJE, 'validado'];

    public static function prioridad(Reporte $reporte): CarbonInterface
    {
        return $reporte->enviado_en ?? $reporte->created_at ?? now();
    }

    /** ¿`$a` llegó antes que `$b`? A igual instante decide el id (el que se creó antes). */
    public static function llegoAntes(Reporte $a, Reporte $b): bool
    {
        $pa = self::prioridad($a);
        $pb = self::prioridad($b);

        return $pa->lt($pb) || ($pa->eq($pb) && $a->id < $b->id);
    }

    /**
     * El primer informe del mismo programa que llegó antes que `$reporte` y sigue en uno de
     * `$estados`: mientras exista, `$reporte` tiene que esperar su turno.
     *
     * @param  array<int, string>  $estados
     */
    public function anteriorPendiente(Reporte $reporte, array $estados): ?Reporte
    {
        $prioridad = self::prioridad($reporte);

        return Reporte::query()
            ->where('programa_id', $reporte->programa_id)
            ->whereKeyNot($reporte->id)
            ->whereIn('estado', $estados)
            ->where(fn ($query) => $query
                ->whereRaw('COALESCE(enviado_en, created_at) < ?', [$prioridad])
                ->orWhere(fn ($empate) => $empate
                    ->whereRaw('COALESCE(enviado_en, created_at) = ?', [$prioridad])
                    ->where('id', '<', $reporte->id)))
            ->orderByRaw('COALESCE(enviado_en, created_at) asc')
            ->orderBy('id')
            ->first();
    }
}

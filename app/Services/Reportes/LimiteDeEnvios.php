<?php

namespace App\Services\Reportes;

use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Reputacion\CheatDetectionService;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Impide que un investigador inunde los programas con informes enviados.
 *
 * A diferencia de {@see CheatDetectionService} (que detecta
 * y sanciona después, desde `reputacion:audit`), este servicio bloquea el envío en el
 * momento. Devuelve el motivo del bloqueo en texto para mostrárselo al investigador,
 * con cuánto debe esperar; `null` si puede enviar.
 *
 * Solo cuentan los informes ya enviados (`enviado_en`): los borradores no pesan.
 */
class LimiteDeEnvios
{
    /**
     * @param  int|null  $exceptoReporteId  El informe que se está enviando: nunca cuenta contra sí mismo.
     */
    public function motivoDeBloqueo(User $investigador, int $programaId, ?string $titulo = null, ?int $exceptoReporteId = null): ?string
    {
        return $this->bloqueoGlobal($investigador, $exceptoReporteId)
            ?? $this->bloqueoPorPrograma($investigador, $programaId, $exceptoReporteId)
            ?? $this->bloqueoPorTituloRepetido($investigador, $programaId, $titulo, $exceptoReporteId);
    }

    /** Demasiados informes enviados en total (mismo umbral que la ráfaga de la auditoría). */
    private function bloqueoGlobal(User $investigador, ?int $exceptoReporteId): ?string
    {
        $ventana = (int) config('reputacion.cheat.rafaga.ventana_minutos', 15);
        $maximo = (int) config('reputacion.cheat.rafaga.max_reportes', 5);

        $enviados = $this->enviadosDesde($investigador, now()->subMinutes($ventana), $exceptoReporteId);

        if ($enviados->count() < $maximo) {
            return null;
        }

        return "Has enviado {$maximo} informes en los últimos {$ventana} minutos. Para evitar el envío masivo, ".
            'espera '.$this->espera($enviados, $ventana).' antes de enviar otro.';
    }

    /** Demasiados informes al mismo programa. */
    private function bloqueoPorPrograma(User $investigador, int $programaId, ?int $exceptoReporteId): ?string
    {
        $ventana = (int) config('reportes.limites_envio.por_programa.ventana_minutos', 60);
        $maximo = (int) config('reportes.limites_envio.por_programa.maximo', 3);

        $enviados = $this->enviadosDesde($investigador, now()->subMinutes($ventana), $exceptoReporteId)
            ->where('programa_id', $programaId);

        if ($enviados->count() < $maximo) {
            return null;
        }

        $programa = Programa::query()->whereKey($programaId)->value('nombre') ?? 'este programa';

        return "Ya enviaste {$maximo} informes a {$programa} en los últimos {$ventana} minutos. ".
            'Espera '.$this->espera($enviados, $ventana).' antes de enviar otro a este programa.';
    }

    /** El mismo título ya se envió a este programa hace poco. */
    private function bloqueoPorTituloRepetido(User $investigador, int $programaId, ?string $titulo, ?int $exceptoReporteId): ?string
    {
        $titulo = trim((string) $titulo);

        if ($titulo === '') {
            return null;
        }

        $horas = (int) config('reportes.limites_envio.titulo_repetido_horas', 24);

        $repetido = Reporte::query()
            ->where('investigador_id', $investigador->id)
            ->where('programa_id', $programaId)
            ->where('enviado_en', '>=', now()->subHours($horas))
            ->when($exceptoReporteId, fn ($consulta) => $consulta->whereKeyNot($exceptoReporteId))
            ->whereRaw('lower(titulo) = ?', [mb_strtolower($titulo)])
            ->exists();

        return $repetido
            ? "Ya enviaste a este programa un informe con el mismo título en las últimas {$horas} horas. ".
                'Si es otro hallazgo, dale un título distinto; si es el mismo, no hace falta enviarlo de nuevo.'
            : null;
    }

    /**
     * Informes enviados por el investigador desde `$desde`, del más antiguo al más reciente.
     *
     * @return Collection<int, Reporte>
     */
    private function enviadosDesde(User $investigador, DateTimeInterface $desde, ?int $exceptoReporteId): Collection
    {
        return Reporte::query()
            ->where('investigador_id', $investigador->id)
            ->where('enviado_en', '>=', $desde)
            ->when($exceptoReporteId, fn ($consulta) => $consulta->whereKeyNot($exceptoReporteId))
            ->orderBy('enviado_en')
            ->get(['id', 'programa_id', 'enviado_en']);
    }

    /**
     * Tiempo hasta que el envío más antiguo de la ventana caduque y libere un hueco.
     *
     * @param  Collection<int, Reporte>  $enviados
     */
    private function espera(Collection $enviados, int $ventanaMinutos): string
    {
        $libera = Carbon::parse($enviados->first()->enviado_en)->addMinutes($ventanaMinutos);
        $minutos = max(1, (int) ceil(now()->diffInMinutes($libera, false)));

        return $minutos === 1 ? 'aproximadamente 1 minuto' : "aproximadamente {$minutos} minutos";
    }
}

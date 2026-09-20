<?php

namespace App\Services\Moderacion;

use App\Models\Programa;
use App\Models\Reporte;
use Illuminate\Database\Eloquent\Builder;

/**
 * Los informes de un programa tal como los ve un revisor (moderador o admin):
 * consulta filtrada y ordenada, conteos por vista y resumen de cada informe.
 * Los borradores del investigador nunca forman parte de la cola.
 */
class ColaDeInformes
{
    public const FILTROS = ['por_revisar', 'en_revision', 'aprobados', 'rechazados', 'todos'];

    public static function filtro(mixed $valor): string
    {
        return in_array($valor, self::FILTROS, true) ? (string) $valor : 'por_revisar';
    }

    /**
     * @return Builder<Reporte>
     */
    public function consulta(Programa $programa, string $filtro): Builder
    {
        return Reporte::query()
            ->where('programa_id', $programa->id)
            ->where('estado', '!=', 'borrador')
            ->with([
                'programa:id,nombre',
                'investigador' => fn ($query) => $query
                    ->select('id', 'name', 'reputation_score')
                    ->withCount(['reportes as reportes_descartados' => fn ($descartados) => $descartados->whereIn('estado', Reporte::ESTADOS_RECHAZADOS)]),
                'asignadoA:id,name',
            ])
            ->when($filtro === 'por_revisar', fn ($query) => $query->where('estado', 'enviado'))
            ->when($filtro === 'en_revision', fn ($query) => $query->where('estado', 'en_revision'))
            ->when($filtro === 'aprobados', fn ($query) => $query->whereIn('estado', Reporte::ESTADOS_APROBADOS))
            ->when($filtro === 'rechazados', fn ($query) => $query->whereIn('estado', Reporte::ESTADOS_RECHAZADOS))
            // La cola pendiente se atiende por orden de llegada; el resto, lo más reciente primero.
            ->when(
                in_array($filtro, ['por_revisar', 'en_revision'], true),
                fn ($query) => $query->orderBy('enviado_en')->orderBy('id'),
                fn ($query) => $query->latest('id'),
            );
    }

    /**
     * @return array{por_revisar: int, en_revision: int, aprobados: int, rechazados: int, todos: int}
     */
    public function conteos(Programa $programa): array
    {
        $recibidos = fn () => $programa->reportes()->where('estado', '!=', 'borrador');

        return [
            'por_revisar' => $recibidos()->where('estado', 'enviado')->count(),
            'en_revision' => $recibidos()->where('estado', 'en_revision')->count(),
            'aprobados' => $recibidos()->whereIn('estado', Reporte::ESTADOS_APROBADOS)->count(),
            'rechazados' => $recibidos()->whereIn('estado', Reporte::ESTADOS_RECHAZADOS)->count(),
            'todos' => $recibidos()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resumen(Reporte $reporte): array
    {
        return [
            'id' => $reporte->id,
            'numero_reporte' => $reporte->numero_reporte,
            'titulo' => $reporte->titulo,
            'estado' => $reporte->estado->value,
            'severidad' => $reporte->severidad?->value,
            'categoria' => $reporte->categoria,
            'programa_nombre' => $reporte->programa->nombre,
            'enviado_en' => $reporte->enviado_en?->toISOString(),
            'es_duplicado_de' => $reporte->es_duplicado_de,
            'asignado_a' => $reporte->asignadoA?->only(['id', 'name']),
            'investigador' => [
                'id' => $reporte->investigador->id,
                'name' => $reporte->investigador->name,
                'reputation_score' => $reporte->investigador->reputation_score,
                'reportes_descartados' => (int) $reporte->investigador->getAttribute('reportes_descartados'),
            ],
        ];
    }
}

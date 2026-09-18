<?php

namespace App\Services\Reputacion;

use App\Enums\GravedadSancion;
use App\Models\Reporte;
use App\Models\Sancion;
use App\Models\User;
use App\Services\Reputacion\DataObjects\Deteccion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Detector de comportamientos fraudulentos de investigadores.
 *
 * Reconoce tres patrones orientados a "inflar métricas" con falsos positivos:
 *
 *  - Ráfagas: muchos reportes enviados en una misma ventana corta.
 *  - Duplicados: tasa de reportes marcados como duplicados sobre los enviados.
 *  - Fabricación: reportes altos/críticos terminados en estado sospechoso sin
 *    una PoC válida.
 *
 * `analizar*` devuelven {@see Deteccion} sin efectos; `ejecutar` aplica las
 * sanciones proporcionales (con escalación por reincidencia) a través de
 * {@see ReputationService}.
 */
class CheatDetectionService
{
    public function __construct(private readonly ReputationService $reputacion) {}

    /**
     * Detecta una ráfaga de reportes enviados dentro de la ventana configurada.
     */
    public function analizarRafaga(User $usuario): ?Deteccion
    {
        $ventana = (int) config('reputacion.cheat.rafaga.ventana_minutos', 15);
        $maximo = (int) config('reputacion.cheat.rafaga.max_reportes', 5);

        $desde = now()->subMinutes($ventana);
        $cantidad = Reporte::query()
            ->where('investigador_id', $usuario->id)
            ->where('enviado_en', '>=', $desde)
            ->count();

        if ($cantidad <= $maximo) {
            return null;
        }

        return new Deteccion(
            motivo: 'rafaga_reportes',
            gravedad: GravedadSancion::Media,
            mensaje: "Envió {$cantidad} reportes en los últimos {$ventana} minutos.",
            metadata: [
                'reportes' => $cantidad,
                'ventana_minutos' => $ventana,
                'maximo' => $maximo,
            ],
        );
    }

    /**
     * Detecta una tasa de duplicados por encima del umbral configurado.
     */
    public function analizarDuplicados(User $usuario): ?Deteccion
    {
        $minimo = (int) config('reputacion.cheat.duplicados.minimo', 3);
        $tasaMax = (float) config('reputacion.cheat.duplicados.tasa_max', 0.5);

        $enviados = Reporte::query()
            ->where('investigador_id', $usuario->id)
            ->whereNotNull('enviado_en')
            ->count();

        $duplicados = Reporte::query()
            ->where('investigador_id', $usuario->id)
            ->where(fn (Builder $query): Builder => $query
                ->where('estado', 'duplicado')
                ->orWhereNotNull('es_duplicado_de'))
            ->count();

        if ($enviados < $minimo || $duplicados < $minimo) {
            return null;
        }

        $tasa = $duplicados / $enviados;

        if ($tasa < $tasaMax) {
            return null;
        }

        return new Deteccion(
            motivo: 'reporte_duplicado',
            gravedad: GravedadSancion::Media,
            mensaje: sprintf('Tiene %d de %d reportes marcados como duplicados (%.0f%%).', $duplicados, $enviados, $tasa * 100),
            metadata: [
                'duplicados' => $duplicados,
                'enviados' => $enviados,
                'tasa' => round($tasa, 4),
            ],
        );
    }

    /**
     * Detecta reportes de severidad alta/crítica terminados en un estado
     * sospechoso sin una PoC válida.
     *
     * @return list<Deteccion>
     */
    public function analizarFabricaciones(User $usuario): array
    {
        $severidades = config('reputacion.cheat.fabricacion.severidades', ['alta', 'critica']);
        $requierePoc = (bool) config('reputacion.cheat.fabricacion.requiere_poc', true);
        $estados = config('reputacion.cheat.fabricacion.estados_sospechosos', ['duplicado', 'fuera_de_alcance', 'rechazado']);

        $reportes = Reporte::query()
            ->where('investigador_id', $usuario->id)
            ->whereIn('estado', $estados)
            ->whereIn('severidad', $severidades)
            ->get();

        $detecciones = [];

        foreach ($reportes as $reporte) {
            $sinPoc = $requierePoc && ($reporte->poc === null || $reporte->poc === []);

            if (! $sinPoc) {
                continue;
            }

            $detecciones[] = new Deteccion(
                motivo: 'fabricacion_evidencia',
                gravedad: GravedadSancion::Grave,
                mensaje: sprintf(
                    'Reporte %s (#%d) de severidad %s sin PoC válida.',
                    $reporte->numero_reporte,
                    $reporte->id,
                    $reporte->severidad->value,
                ),
                reporte_id: $reporte->id,
                metadata: [
                    'severidad' => $reporte->severidad->value,
                    'estado' => $reporte->estado->value,
                ],
            );
        }

        return $detecciones;
    }

    /**
     * Detecta la fabricación de un reporte concreto (sin agregar métricas del
     * usuario).
     *
     * @return list<Deteccion>
     */
    public function analizar(Reporte $reporte): array
    {
        $requierePoc = (bool) config('reputacion.cheat.fabricacion.requiere_poc', true);
        $severidades = config('reputacion.cheat.fabricacion.severidades', ['alta', 'critica']);
        $estados = config('reputacion.cheat.fabricacion.estados_sospechosos', ['duplicado', 'fuera_de_alcance', 'rechazado']);

        if (! in_array($reporte->estado->value, $estados, true) || ! in_array($reporte->severidad->value, $severidades, true)) {
            return [];
        }

        $sinPoc = $requierePoc && ($reporte->poc === null || $reporte->poc === []);

        if (! $sinPoc) {
            return [];
        }

        return [$this->deteccionFabricacion($reporte)];
    }

    /**
     * Agrega todas las detecciones del usuario.
     *
     * @return list<Deteccion>
     */
    public function analizarUsuario(User $usuario): array
    {
        $detecciones = [];

        $rafaga = $this->analizarRafaga($usuario);

        if ($rafaga !== null) {
            $detecciones[] = $rafaga;
        }

        $duplicados = $this->analizarDuplicados($usuario);

        if ($duplicados !== null) {
            $detecciones[] = $duplicados;
        }

        foreach ($this->analizarFabricaciones($usuario) as $deteccion) {
            $detecciones[] = $deteccion;
        }

        return $detecciones;
    }

    /**
     * Aplica una sanción proporcional por cada detección del usuario.
     *
     * @return list<Sancion>
     */
    public function ejecutar(User|int $usuario): array
    {
        $usuario = $usuario instanceof User ? $usuario : User::query()->findOrFail($usuario);

        $aplicadas = [];

        foreach ($this->analizarUsuario($usuario) as $deteccion) {
            if ($this->yaSancionado($deteccion, $usuario)) {
                continue;
            }

            $reporte = $deteccion->reporte_id !== null
                ? Reporte::query()->find($deteccion->reporte_id)
                : null;

            $aplicadas[] = $this->reputacion->aplicarSancion(
                $usuario,
                $deteccion->motivo,
                $deteccion->gravedad,
                $reporte,
                $deteccion->metadata,
            );
        }

        return $aplicadas;
    }

    private function deteccionFabricacion(Reporte $reporte): Deteccion
    {
        return new Deteccion(
            motivo: 'fabricacion_evidencia',
            gravedad: GravedadSancion::Grave,
            mensaje: sprintf(
                'Reporte %s (#%d) de severidad %s sin PoC válida.',
                $reporte->numero_reporte,
                $reporte->id,
                $reporte->severidad->value,
            ),
            reporte_id: $reporte->id,
            metadata: [
                'severidad' => $reporte->severidad->value,
                'estado' => $reporte->estado->value,
            ],
        );
    }

    /**
     * Evita sancionar dos veces la misma conducta: para fabricación se compara
     * la sanción vigente del mismo reporte y motivo; para detecciones
     * agregadas, cualquier sanción vigente del mismo motivo dentro de la
     * ventana de idempotencia.
     */
    private function yaSancionado(Deteccion $deteccion, User $usuario): bool
    {
        $query = Sancion::query()
            ->where('usuario_id', $usuario->id)
            ->where('motivo', $deteccion->motivo)
            ->whereIn('estado', ['aplicada', 'apelada']);

        if ($deteccion->reporte_id !== null) {
            $query->where('reporte_id', $deteccion->reporte_id);
        } else {
            $minutos = (int) config('reputacion.cheat.idempotencia_minutos', 1440);
            $query->where('created_at', '>=', now()->subMinutes($minutos));
        }

        return $query->exists();
    }
}

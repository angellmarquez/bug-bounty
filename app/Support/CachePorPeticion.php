<?php

namespace App\Support;

use Closure;

/**
 * Recuerda resultados de consultas durante una sola petición HTTP.
 *
 * Una página evalúa el ABAC y los datos del usuario muchas veces (FormRequest, controlador,
 * permisos de cada botón, props compartidas). Contra una base remota (~90 ms por consulta) esas
 * repeticiones sumaban varios segundos por página.
 *
 * - Vive en el Request, que es nuevo en cada petición (también en los tests).
 * - En consola de larga vida (queue:work, schedule:work) no guarda nada.
 * - Cualquier escritura en una tabla que no sea de bitácora o infraestructura la vacía
 *   (ver AppServiceProvider): una sanción o un cambio de rol a mitad de petición nunca deja
 *   un permiso desactualizado.
 */
final class CachePorPeticion
{
    private const ATRIBUTO = 'cache_por_peticion';

    /** Tablas cuyas escrituras no cambian datos de permisos: bitácoras e infraestructura. */
    private const TABLAS_SIN_EFECTO = ['auditorias', 'sessions', 'cache', 'cache_locks', 'jobs', 'failed_jobs', 'notifications', 'eventos_reporte'];

    /**
     * @template T
     *
     * @param  Closure(): T  $calcular
     * @return T
     */
    public static function recordar(string $clave, Closure $calcular): mixed
    {
        if (! self::activa()) {
            return $calcular();
        }

        // Una suspensión o un plazo vencen con el paso del tiempo, sin escribir nada: lo recordado
        // vale solo dentro del mismo minuto.
        $clave .= '@'.now()->format('YmdHi');
        $atributos = request()->attributes;
        /** @var array<string, mixed> $cache */
        $cache = $atributos->get(self::ATRIBUTO, []);

        if (array_key_exists($clave, $cache)) {
            return $cache[$clave];
        }

        $generacion = self::$generacion;
        $valor = $calcular();

        // Si el cálculo escribió en la base, lo calculado puede ser previo a esa escritura: no se guarda.
        if ($generacion === self::$generacion) {
            $atributos->set(self::ATRIBUTO, [...$atributos->get(self::ATRIBUTO, []), $clave => $valor]);
        }

        return $valor;
    }

    private static int $generacion = 0;

    public static function olvidar(): void
    {
        self::$generacion++;

        if (app()->bound('request')) {
            request()->attributes->remove(self::ATRIBUTO);
        }
    }

    /** ¿Esta sentencia SQL escribe en una tabla que puede cambiar lo recordado? */
    public static function invalidaCon(string $sql): bool
    {
        if (preg_match('/^\s*(?:insert\s+into|update|delete\s+from)\s+["`]?(\w+)/i', $sql, $m) !== 1) {
            // Las lecturas no invalidan; cualquier otra escritura, por si acaso, sí.
            return preg_match('/^\s*(insert|update|delete|merge|truncate|alter|drop)\b/i', $sql) === 1;
        }

        return ! in_array(strtolower($m[1]), self::TABLAS_SIN_EFECTO, true);
    }

    private static function activa(): bool
    {
        return app()->bound('request') && (! app()->runningInConsole() || app()->runningUnitTests());
    }
}

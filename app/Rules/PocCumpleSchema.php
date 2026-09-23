<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que los datos de la PoC de un reporte cumplan el `poc_schema` definido
 * por el programa: campos obligatorios presentes, tipos coherentes (url/number/select)
 * y ninguna clave ajena al schema (evita que el investigador cuele datos no declarados).
 *
 * Replica en servidor la misma lógica que `resources/js/lib/poc-schema.ts` (`validarPoc`)
 * aplica en el cliente, porque esa validación es evitable con una petición directa.
 */
class PocCumpleSchema implements ValidationRule
{
    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @param  bool  $exigirRequeridos  false mientras el reporte es un borrador en progreso:
     *                                  se valida el formato de lo ya escrito, pero no se bloquea
     *                                  el guardado por campos obligatorios aún vacíos. Al enviar
     *                                  el reporte (ver ReporteController::enviar) se exige true.
     */
    public function __construct(
        private readonly array $schema,
        private readonly bool $exigirRequeridos = true,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('La prueba de concepto debe enviarse como datos estructurados.');

            return;
        }

        // Programa sin campos personalizados: la PoC es texto libre, así que no hay
        // claves que exigir. Solo se comprueba que no venga vacía (y solo al enviar).
        if ($this->schema === []) {
            if ($this->exigirRequeridos && ! $this->tieneContenido($value)) {
                $fail('Agrega la prueba de concepto: describe cómo reproducir la vulnerabilidad.');
            }

            return;
        }

        $nombresValidos = array_map(
            static fn (array $campo): string => (string) $campo['name'],
            $this->schema,
        );

        foreach (array_keys($value) as $clave) {
            if (! in_array($clave, $nombresValidos, true)) {
                $fail("El campo de PoC «{$clave}» no está definido en este programa.");

                return;
            }
        }

        foreach ($this->schema as $campo) {
            $this->validarCampo($campo, $value, $fail);
        }
    }

    /**
     * @param  array<string, mixed>  $campo
     * @param  array<string, mixed>  $datos
     */
    private function validarCampo(array $campo, array $datos, Closure $fail): void
    {
        $nombre = (string) $campo['name'];
        $etiqueta = (string) ($campo['label'] ?? $nombre);
        $tipo = (string) ($campo['type'] ?? 'text');
        $obligatorio = (bool) ($campo['required'] ?? false);
        $repetible = (bool) ($campo['repeatable'] ?? false);
        $opciones = array_map(
            static fn (array $opcion): string => (string) $opcion['value'],
            $campo['options'] ?? [],
        );

        $dato = $datos[$nombre] ?? null;

        if ($repetible) {
            $items = is_array($dato) ? $dato : [];
            $noVacios = array_filter(
                $items,
                static fn ($item): bool => is_string($item) && trim($item) !== '',
            );

            if ($this->exigirRequeridos && $obligatorio && $noVacios === []) {
                $fail("{$etiqueta} requiere al menos un elemento.");
            }

            foreach ($items as $item) {
                if (! is_string($item)) {
                    $fail("{$etiqueta} tiene un valor inválido.");

                    continue;
                }

                $this->validarValor($item, $tipo, $opciones, $etiqueta, $fail);
            }

            return;
        }

        $esVacio = $dato === null || $dato === '' || (is_string($dato) && trim($dato) === '');

        if ($this->exigirRequeridos && $obligatorio && $esVacio) {
            $fail("{$etiqueta} es obligatorio.");

            return;
        }

        if ($esVacio) {
            return;
        }

        if (! is_string($dato) && ! is_numeric($dato)) {
            $fail("{$etiqueta} tiene un valor inválido.");

            return;
        }

        $this->validarValor((string) $dato, $tipo, $opciones, $etiqueta, $fail);
    }

    /**
     * @param  array<int, string>  $opciones
     */
    private function validarValor(string $valor, string $tipo, array $opciones, string $etiqueta, Closure $fail): void
    {
        if ($valor === '') {
            return;
        }

        if ($tipo === 'url' && filter_var($valor, FILTER_VALIDATE_URL) === false) {
            $fail("{$etiqueta} debe ser una URL válida.");

            return;
        }

        if ($tipo === 'number' && ! is_numeric($valor)) {
            $fail("{$etiqueta} debe ser un número.");

            return;
        }

        if ($tipo === 'select' && $opciones !== [] && ! in_array($valor, $opciones, true)) {
            $fail("{$etiqueta} tiene una opción inválida.");
        }
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function tieneContenido(array $value): bool
    {
        foreach ($value as $item) {
            if (is_array($item)) {
                if ($this->tieneContenido($item)) {
                    return true;
                }

                continue;
            }

            if (is_numeric($item) || (is_string($item) && trim($item) !== '')) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Concerns;

use App\Models\Programa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Validator;

/**
 * Reglas de las recompensas de un programa (crear y editar). Los pagos son siempre en USDC y la
 * tabla por severidad es la que se publica y la que se sugiere al asignar el bounty: tiene que
 * ser coherente con el rango del programa.
 */
trait RecompensasValidationRules
{
    /** Severidades de la tabla, de mayor a menor. */
    private const SEVERIDADES_TABLA = ['critica', 'alta', 'media', 'baja'];

    private const MONTO_MAXIMO = 1000000;

    /**
     * Las casillas vacías de la tabla no se guardan (se publicaría una severidad sin monto);
     * si no queda ninguna, el programa no tiene tabla.
     */
    protected function normalizarTablaRecompensas(): void
    {
        $tabla = $this->input('tabla_recompensas');
        if (! is_array($tabla)) {
            return;
        }

        $tabla = array_filter($tabla, fn ($monto) => $monto !== null && $monto !== '');
        $this->merge(['tabla_recompensas' => $tabla === [] ? null : $tabla]);
    }

    /**
     * @return array<string, array<int, string|ValidationRule|RequiredIf>>
     */
    protected function recompensasRules(): array
    {
        return [
            // Con recompensas hay que decir al menos hasta cuánto se paga.
            'recompensa_max' => [
                Rule::requiredIf(fn () => $this->has('tiene_recompensas') && $this->boolean('tiene_recompensas') && $this->recompensaActual('recompensa_max') === null),
                'nullable', 'numeric', 'min:0', 'max:'.self::MONTO_MAXIMO,
            ],
            'recompensa_min' => ['nullable', 'numeric', 'min:0', 'max:'.self::MONTO_MAXIMO],
            'moneda' => ['nullable', 'string', Rule::in(['USDC'])],
            'tabla_recompensas' => ['nullable', 'array:'.implode(',', self::SEVERIDADES_TABLA)],
            'tabla_recompensas.*' => ['nullable', 'numeric', 'min:0', 'max:'.self::MONTO_MAXIMO],
        ];
    }

    /** @return array<string, string> */
    protected function recompensasMessages(): array
    {
        return [
            'recompensa_max.required' => 'Indica la recompensa máxima que paga el programa.',
            'recompensa_min.min' => 'La recompensa mínima no puede ser negativa.',
            'recompensa_max.min' => 'La recompensa máxima no puede ser negativa.',
            'moneda.in' => 'Los pagos de la plataforma son solo en USDC.',
            'tabla_recompensas.array' => 'La tabla de recompensas solo admite las severidades crítica, alta, media y baja.',
            'tabla_recompensas.*.numeric' => 'Cada monto de la tabla de recompensas debe ser un número.',
            'tabla_recompensas.*.min' => 'Los montos de la tabla de recompensas no pueden ser negativos.',
            'tabla_recompensas.*.max' => 'Los montos de la tabla de recompensas no pueden superar '.self::MONTO_MAXIMO.' USDC.',
        ];
    }

    /**
     * El rango y la tabla se comparan con lo que quedará guardado: lo enviado o, al editar, lo actual.
     */
    protected function validarCoherenciaDeRecompensas(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['recompensa_min', 'recompensa_max', 'tabla_recompensas', 'tabla_recompensas.*'])) {
            return;
        }

        $min = $this->recompensaActual('recompensa_min');
        $max = $this->recompensaActual('recompensa_max');

        if ($min !== null && $max !== null && $max < $min) {
            $validator->errors()->add('recompensa_max', 'La recompensa máxima debe ser mayor o igual que la mínima.');

            return;
        }

        $tabla = $this->has('tabla_recompensas') ? $this->input('tabla_recompensas') : null;
        if (! is_array($tabla)) {
            return;
        }

        $anterior = null;
        foreach (self::SEVERIDADES_TABLA as $severidad) {
            if (! isset($tabla[$severidad]) || $tabla[$severidad] === '') {
                continue;
            }
            $monto = (float) $tabla[$severidad];
            $nombre = ['critica' => 'crítica', 'alta' => 'alta', 'media' => 'media', 'baja' => 'baja'][$severidad];

            if ($min !== null && $monto < $min) {
                $validator->errors()->add('tabla_recompensas', "La recompensa {$nombre} ({$monto} USDC) es menor que la mínima del programa ({$min} USDC).");

                return;
            }
            if ($max !== null && $monto > $max) {
                $validator->errors()->add('tabla_recompensas', "La recompensa {$nombre} ({$monto} USDC) supera la máxima del programa ({$max} USDC).");

                return;
            }
            if ($anterior !== null && $monto > $anterior) {
                $validator->errors()->add('tabla_recompensas', 'Una severidad menor no puede pagar más que una mayor (crítica ≥ alta ≥ media ≥ baja).');

                return;
            }
            $anterior = $monto;
        }
    }

    /** El valor que quedará guardado: el enviado o, al editar, el del programa. */
    private function recompensaActual(string $campo): ?float
    {
        if ($this->has($campo)) {
            $valor = $this->input($campo);

            return $valor === null || $valor === '' ? null : (float) $valor;
        }

        $programa = $this->route('programa');
        $actual = $programa instanceof Programa ? $programa->{$campo} : null;

        return $actual === null ? null : (float) $actual;
    }
}

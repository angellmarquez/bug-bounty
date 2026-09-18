<?php

namespace App\Abac;

use Illuminate\Support\Collection;

/**
 * Evalúa los grupos de condiciones de una regla ABAC.
 *
 * Cada grupo es un mapa `atributo => [operador => valor esperado]`. Las
 * condiciones del mismo grupo se combinan con AND y los valores esperados
 * admiten referencias `@sujeto.*`, `@objeto.*` y `@entorno.*`.
 */
class EvaluadorCondiciones
{
    public const OPERADORES = [
        '=',
        '==',
        '!=',
        'in',
        'not_in',
        'contains',
        '>',
        '>=',
        '<',
        '<=',
        'is_null',
        'is_not_null',
    ];

    /**
     * Indica si el grupo de condiciones se cumple para el contexto dado.
     *
     * @param  array<string, mixed>  $condiciones
     */
    public function cumple(array $condiciones, string $grupo, ContextoAbac $contexto): bool
    {
        foreach ($condiciones as $atributo => $operadores) {
            if (! is_array($operadores)) {
                throw new AbacException(sprintf(
                    'La condición [%s] de la regla debe declarar operadores.',
                    $atributo,
                ));
            }

            $operadores = self::normalizarOperadores($operadores);

            [$existe, $valor] = $contexto->valor($grupo, (string) $atributo);

            if ($existe === false && $this->requiereExistencia($operadores)) {
                return false;
            }

            foreach ($operadores as $operador => $esperado) {
                $esperado = $this->resolverReferencias($esperado, $contexto);

                if (! $this->aplica((string) $operador, $valor, $esperado)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Normaliza la forma compacta `['op1', 'op2']` a `['op1' => true, 'op2' => true]`.
     *
     * @param  array<int|string, mixed>  $operadores
     * @return array<string, mixed>
     */
    public static function normalizarOperadores(array $operadores): array
    {
        $normalizados = [];

        if (array_is_list($operadores)) {
            foreach ($operadores as $operador) {
                $normalizados[(string) $operador] = true;
            }

            return $normalizados;
        }

        foreach ($operadores as $operador => $valor) {
            $normalizados[(string) $operador] = $valor;
        }

        return $normalizados;
    }

    /**
     * Comprueba que un operador aplica sobre el valor del atributo.
     */
    private function aplica(string $operador, mixed $valor, mixed $esperado): bool
    {
        return match ($operador) {
            '=', '==' => $this->igual($valor, $esperado),
            '!=' => ! $this->igual($valor, $esperado),
            'in' => is_array($esperado) && $this->hayIgual($esperado, $valor),
            'not_in' => is_array($esperado) && ! $this->hayIgual($esperado, $valor),
            'contains' => $this->contiene($valor, $esperado),
            '>' => $this->comparar($valor, $esperado) > 0,
            '>=' => $this->comparar($valor, $esperado) >= 0,
            '<' => $this->comparar($valor, $esperado) < 0,
            '<=' => $this->comparar($valor, $esperado) <= 0,
            'is_null' => $valor === null,
            'is_not_null' => $valor !== null,
            default => throw new AbacException(sprintf('Operador ABAC no soportado: [%s].', $operador)),
        };
    }

    /**
     * Resuelve referencias `@sujeto.x`, `@objeto.x` y `@entorno.x` en los valores esperados.
     */
    private function resolverReferencias(mixed $valor, ContextoAbac $contexto): mixed
    {
        if (is_string($valor) && str_starts_with($valor, '@')) {
            if (str_starts_with($valor, '@@')) {
                return substr($valor, 1);
            }

            $partes = explode('.', substr($valor, 1), 2);
            $grupo = $partes[0];
            $ruta = $partes[1] ?? '';

            return $contexto->valor($grupo, $ruta)[1] ?? null;
        }

        if (is_array($valor)) {
            $resolvido = [];

            foreach ($valor as $clave => $elemento) {
                $resolvido[$clave] = $this->resolverReferencias($elemento, $contexto);
            }

            return $resolvido;
        }

        return $valor;
    }

    private function igual(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a == (float) $b;
        }

        if (is_array($a) || is_array($b)) {
            return $a == $b;
        }

        if ($this->esFecha((string) $a) && $this->esFecha((string) $b)) {
            return strtotime((string) $a) === strtotime((string) $b);
        }

        return (string) $a === (string) $b;
    }

    private function comparar(mixed $a, mixed $b): int
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }

        if (is_bool($a) || is_bool($b)) {
            return ((int) (bool) $a) <=> ((int) (bool) $b);
        }

        if ($this->esFecha((string) $a) && $this->esFecha((string) $b)) {
            return strtotime((string) $a) <=> strtotime((string) $b);
        }

        return strcmp((string) $a, (string) $b);
    }

    /**
     * Determina si la lista contiene un valor equivalente.
     *
     * @param  array<int|string, mixed>  $lista
     */
    private function hayIgual(array $lista, mixed $valor): bool
    {
        foreach ($lista as $elemento) {
            if ($this->igual($elemento, $valor)) {
                return true;
            }
        }

        return false;
    }

    private function contiene(mixed $atributo, mixed $esperado): bool
    {
        if (is_array($atributo)) {
            return $this->hayIgual($atributo, $esperado);
        }

        if ($atributo instanceof Collection) {
            return $atributo->contains(fn (mixed $elemento): bool => $this->igual($elemento, $esperado));
        }

        if (is_string($atributo) && is_scalar($esperado)) {
            return str_contains($atributo, (string) $esperado);
        }

        return false;
    }

    private function esFecha(string $valor): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/', $valor) === 1;
    }

    /**
     * Determina si la condición exige que el atributo exista explícitamente:
     * un atributo ausente debe tratarse como «no coincide» salvo en is_null.
     *
     * @param  array<string, mixed>  $operadores
     */
    private function requiereExistencia(array $operadores): bool
    {
        foreach ($operadores as $operador => $_) {
            if ($operador !== 'is_null') {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Abac;

use App\Models\User;

/**
 * Motor de autorización ABAC de la plataforma.
 *
 * Evalúa una acción contra las reglas declaradas en config/abac.php a partir
 * de los atributos de sujeto (usuario), objeto (recurso) y entorno (contexto).
 *
 * Semántica de decisión:
 *  - Las reglas se ordenan por `prioridad` (menor primero).
 *  - Si alguna regla que coincide es `denegar`, la decisión es denegar (fail-closed).
 *  - Si ninguna coincide y `deny_by_default` está activo, se deniega.
 */
class AbacEngine
{
    public function __construct(
        private readonly AtributosAbac $atributos,
        private readonly EvaluadorCondiciones $evaluador,
    ) {}

    /**
     * Evalúa una acción para un usuario, objeto y entorno dados.
     *
     * @param  array<string, mixed>  $entorno
     */
    public function evaluar(string $accion, mixed $objeto = null, ?User $usuario = null, array $entorno = []): AbacDecision
    {
        $this->validarReglas();

        $contexto = $this->contexto($accion, $objeto, $usuario, $entorno);
        $detalle = [];
        $permitida = null;
        $denegada = null;

        foreach ($this->reglas() as $regla) {
            $acciones = $regla['acciones'] ?? [];
            $acciones = is_array($acciones) ? $acciones : [];

            if (! $this->aplicaAccion($accion, $acciones)) {
                continue;
            }

            [$coincide, $grupos] = $this->coincide($regla, $contexto);

            $detalle[] = [
                'regla' => (string) $regla['id'],
                'decision' => (string) ($regla['decision'] ?? 'permitir'),
                'grupos' => $grupos,
                'coincide' => $coincide,
            ];

            if (! $coincide) {
                continue;
            }

            if (($regla['decision'] ?? 'permitir') === 'denegar') {
                $denegada ??= $regla;

                continue;
            }

            $permitida ??= $regla;
        }

        if ($denegada !== null) {
            return AbacDecision::denegar((string) $denegada['id'], $detalle);
        }

        if ($permitida !== null) {
            return AbacDecision::permitir((string) $permitida['id'], $detalle);
        }

        return config('abac.deny_by_default', true)
            ? AbacDecision::denegar(null, $detalle)
            : AbacDecision::noAplicable($detalle);
    }

    /**
     * Construye el contexto completo de la evaluación.
     *
     * @param  array<string, mixed>  $entorno
     */
    public function contexto(string $accion, mixed $objeto = null, ?User $usuario = null, array $entorno = []): ContextoAbac
    {
        return new ContextoAbac(
            accion: $accion,
            usuario: $usuario,
            objeto: $objeto,
            sujeto: $this->atributos->sujeto($usuario),
            objetoAttrs: $this->atributos->objeto($objeto),
            entorno: $this->atributos->entorno($entorno),
            atributos: $this->atributos,
        );
    }

    /**
     * Valida la configuración de reglas; lanza excepción si alguna es inválida.
     *
     * Se invoca en el arranque para fallar rápido ante políticas rotas.
     *
     * @param  array<int, array<string, mixed>>|null  $reglas
     */
    public function validarReglas(?array $reglas = null): void
    {
        foreach ($reglas ?? config('abac.reglas', []) as $indice => $regla) {
            if (! is_array($regla) || ! isset($regla['id']) || ! is_string($regla['id']) || $regla['id'] === '') {
                throw new AbacException(sprintf('La regla %d carece de id.', $indice));
            }

            if (! isset($regla['acciones']) || ! is_array($regla['acciones']) || $regla['acciones'] === []) {
                throw new AbacException(sprintf('La regla [%s] debe declarar al menos una acción.', $regla['id']));
            }

            foreach ($regla['acciones'] as $accion) {
                if (! is_string($accion) || $accion === '') {
                    throw new AbacException(sprintf('La regla [%s] declara una acción inválida.', $regla['id']));
                }
            }

            if (isset($regla['prioridad']) && ! is_int($regla['prioridad'])) {
                throw new AbacException(sprintf('La regla [%s] declara una prioridad no numérica.', $regla['id']));
            }

            $decision = $regla['decision'] ?? 'permitir';

            if (! in_array($decision, ['permitir', 'denegar'], true)) {
                throw new AbacException(sprintf('La regla [%s] declara una decisión desconocida [%s].', $regla['id'], (string) $decision));
            }

            foreach (['sujeto', 'objeto', 'entorno'] as $grupo) {
                $condiciones = $regla[$grupo] ?? [];

                if (! is_array($condiciones)) {
                    throw new AbacException(sprintf('La regla [%s] declara un grupo [%s] inválido.', $regla['id'], $grupo));
                }

                foreach ($condiciones as $atributo => $operadores) {
                    if (! is_array($operadores)) {
                        throw new AbacException(sprintf('La regla [%s] declara la condición [%s] sin operadores.', $regla['id'], $atributo));
                    }

                    foreach (EvaluadorCondiciones::normalizarOperadores($operadores) as $operador => $_) {
                        if (! in_array($operador, EvaluadorCondiciones::OPERADORES, true)) {
                            throw new AbacException(sprintf(
                                'La regla [%s] usa el operador [%s] no soportado.',
                                $regla['id'],
                                $operador,
                            ));
                        }
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $regla
     * @return array{0: bool, 1: array<string, bool>}
     */
    private function coincide(array $regla, ContextoAbac $contexto): array
    {
        $grupos = [];
        $ok = true;

        foreach (['sujeto', 'objeto', 'entorno'] as $grupo) {
            $condiciones = $regla[$grupo] ?? [];
            $condiciones = is_array($condiciones) ? $condiciones : [];

            $cumple = $this->evaluador->cumple($condiciones, $grupo, $contexto);
            $grupos[$grupo] = $cumple;

            if (! $cumple) {
                $ok = false;
            }
        }

        return [$ok, $grupos];
    }

    /**
     * Determina si una acción casa con los patrones de acciones de una regla.
     *
     * @param  array<int, mixed>  $patrones
     */
    private function aplicaAccion(string $accion, array $patrones): bool
    {
        foreach ($patrones as $patron) {
            $patron = (string) $patron;

            if ($patron === '*') {
                return true;
            }

            if (str_ends_with($patron, '.*') && str_starts_with($accion, substr($patron, 0, -1))) {
                return true;
            }

            if ($patron === $accion) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reglas(): array
    {
        $reglas = config('abac.reglas', []);

        if (! is_array($reglas)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $reglas */
        usort($reglas, static fn (array $a, array $b): int => ($a['prioridad'] ?? 100) <=> ($b['prioridad'] ?? 100));

        return $reglas;
    }
}

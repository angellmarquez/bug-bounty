<?php

namespace App\Abac;

/**
 * Resultado de evaluar una acción contra el motor ABAC.
 *
 * Además de la decisión, conserva la regla que la originó y una traza
 * de evaluación por regla (usada por `abac:audit` y los tests).
 */
final class AbacDecision
{
    /** @param list<array<string, mixed>> $detalle */
    private function __construct(
        public readonly DecisionAbac $decision,
        public readonly ?string $regla = null,
        public readonly array $detalle = [],
    ) {}

    /** @param list<array<string, mixed>> $detalle */
    public static function permitir(?string $regla, array $detalle = []): self
    {
        return new self(DecisionAbac::Permitir, $regla, $detalle);
    }

    /** @param list<array<string, mixed>> $detalle */
    public static function denegar(?string $regla, array $detalle = []): self
    {
        return new self(DecisionAbac::Denegar, $regla, $detalle);
    }

    /** @param list<array<string, mixed>> $detalle */
    public static function noAplicable(array $detalle = []): self
    {
        return new self(DecisionAbac::NoAplicable, null, $detalle);
    }

    public function estaPermitida(): bool
    {
        return $this->decision === DecisionAbac::Permitir;
    }

    public function estaDenegada(): bool
    {
        return $this->decision === DecisionAbac::Denegar;
    }

    public function motivo(): string
    {
        return match ($this->decision) {
            DecisionAbac::Permitir => "Acción permitida por la regla [{$this->regla}].",
            DecisionAbac::Denegar => $this->regla === null
                ? 'Acción denegada: no aplica ninguna regla (fail-closed).'
                : "Acción denegada por la regla [{$this->regla}].",
            DecisionAbac::NoAplicable => 'Sin decisión explícita (deny_by_default desactivado).',
        };
    }
}

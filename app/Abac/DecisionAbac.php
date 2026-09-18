<?php

namespace App\Abac;

/**
 * Decisión de autorización resultante de la evaluación ABAC.
 */
enum DecisionAbac: string
{
    case Permitir = 'permitir';
    case Denegar = 'denegar';
    case NoAplicable = 'no_aplicable';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Permitir => 'permitir',
            self::Denegar => 'denegar',
            self::NoAplicable => 'sin regla aplicable',
        };
    }
}

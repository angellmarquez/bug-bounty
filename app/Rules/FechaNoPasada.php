<?php

namespace App\Rules;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * La fecha no puede ser anterior a hoy. Al editar se pasa el valor actual: si no cambió se
 * acepta, para que un programa que empezó hace meses se pueda seguir editando.
 */
class FechaNoPasada implements ValidationRule
{
    public function __construct(
        private readonly ?CarbonInterface $actual = null,
        private readonly string $etiqueta = 'La fecha',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        try {
            $fecha = Carbon::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return; // La regla `date` ya informa del formato.
        }

        if ($this->actual !== null && $this->actual->isSameDay($fecha)) {
            return;
        }

        if ($fecha->lt(today())) {
            $fail("{$this->etiqueta} no puede ser una fecha pasada.");
        }
    }
}

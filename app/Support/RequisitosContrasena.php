<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Los requisitos de contraseña tal como los aplica el servidor, para mostrarlos en los
 * formularios. Se leen de `Password::defaults()` (ver AppServiceProvider), así el texto
 * del formulario nunca contradice a la validación: en producción son 12 caracteres y en
 * local 8.
 */
final class RequisitosContrasena
{
    /**
     * @return array{minimo: int, maximo: int|null, texto: string, reglas: string}
     */
    public static function paraFormulario(): array
    {
        $regla = Password::defaults();
        $aplicadas = $regla->appliedRules();

        $partes = [];
        if ($aplicadas['mixedCase']) {
            $partes[] = 'mayúsculas y minúsculas';
        } elseif ($aplicadas['letters']) {
            $partes[] = 'letras';
        }
        if ($aplicadas['numbers']) {
            $partes[] = 'números';
        }
        if ($aplicadas['symbols']) {
            $partes[] = 'símbolos';
        }

        $texto = "Mínimo {$aplicadas['min']} caracteres";
        if ($partes !== []) {
            $ultima = array_pop($partes);
            $texto .= ', con '.($partes === [] ? $ultima : implode(', ', $partes).' y '.$ultima);
        }
        $texto .= '.';

        if ($aplicadas['uncompromised']) {
            $texto .= ' No puede ser una contraseña que haya aparecido en filtraciones conocidas.';
        }

        return [
            'minimo' => (int) $aplicadas['min'],
            'maximo' => $aplicadas['max'] === null ? null : (int) $aplicadas['max'],
            'texto' => $texto,
            'reglas' => $regla->toPasswordRulesString(),
        ];
    }
}

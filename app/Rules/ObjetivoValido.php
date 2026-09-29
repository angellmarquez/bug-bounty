<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un objetivo web o API tiene que ser una dirección a la que se pueda apuntar: una URL http(s),
 * un dominio (admite comodín, "*.ejemplo.com") o una IP, con puerto y ruta opcionales. Los
 * objetivos móvil u otro son texto libre (nombre de la app, paquete, etc.).
 */
class ObjetivoValido implements DataAwareRule, ValidationRule
{
    public const PATRON = '~^(https?://)?(\*\.)?(([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}|localhost|(\d{1,3}\.){3}\d{1,3})(:\d{1,5})?(/\S*)?$~i';

    /** @var array<string, mixed> */
    private array $data = [];

    /** @param  array<string, mixed>  $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // objetivos.{i}.valor → objetivos.{i}.tipo
        $tipo = data_get($this->data, preg_replace('/\.valor$/', '.tipo', $attribute));

        if (! in_array($tipo, ['web', 'api'], true) || ! is_string($value)) {
            return;
        }

        if (preg_match(self::PATRON, trim($value)) !== 1) {
            $fail('El objetivo '.($tipo === 'web' ? 'web' : 'API').' debe ser una URL o un dominio (ej.: https://app.ejemplo.com o *.ejemplo.com).');
        }
    }
}

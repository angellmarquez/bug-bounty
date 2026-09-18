<?php

namespace App\Abac;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Contexto inmutable de una evaluación ABAC.
 *
 * Mantiene las entradas crudas (usuario, objeto) y los atributos ya
 * resueltos de sujeto/objeto/entorno, y ofrece acceso por ruta para que
 * las condiciones y las referencias `@grupo.ruta` se resuelvan igual.
 */
final class ContextoAbac
{
    /**
     * @param  array<string, mixed>  $sujeto
     * @param  array<string, mixed>  $objetoAttrs
     * @param  array<string, mixed>  $entorno
     */
    public function __construct(
        public readonly string $accion,
        public readonly ?User $usuario,
        public readonly mixed $objeto,
        public readonly array $sujeto,
        public readonly array $objetoAttrs,
        public readonly array $entorno,
        private readonly AtributosAbac $atributos,
    ) {}

    /**
     * Devuelve [existe, valor] de un atributo de un grupo concreto.
     *
     * @return array{0: bool, 1: mixed}
     */
    public function valor(string $grupo, string $ruta): array
    {
        return match ($grupo) {
            'sujeto' => $this->valorEn($this->sujeto, $ruta),
            'entorno' => $this->valorEn($this->entorno, $ruta),
            'objeto' => $this->objeto instanceof Model && str_contains($ruta, '.')
                ? $this->atributos->valorRutaModelo($this->objeto, $ruta)
                : $this->valorEn($this->objetoAttrs, $ruta),
            default => [false, null],
        };
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array{0: bool, 1: mixed}
     */
    private function valorEn(array $atributos, string $ruta): array
    {
        if (array_key_exists($ruta, $atributos)) {
            return [true, $atributos[$ruta]];
        }

        return [false, null];
    }
}

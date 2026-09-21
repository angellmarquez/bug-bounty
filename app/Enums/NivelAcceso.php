<?php

namespace App\Enums;

/**
 * Exigencia de un programa para dejar reportar: cada nivel pide un rango mínimo de reputación
 * (bronce, plata, oro…), configurable en config/reputacion.php.
 */
enum NivelAcceso: string
{
    case Bajo = 'bajo';
    case Medio = 'medio';
    case Alto = 'alto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Bajo => 'Bajo',
            self::Medio => 'Medio',
            self::Alto => 'Alto',
        };
    }

    /**
     * Clave del rango mínimo que exige este nivel (ver `reputacion.acceso`).
     */
    public function rangoRequerido(): string
    {
        return (string) config("reputacion.acceso.{$this->value}");
    }
}

<?php

namespace App\Services\Reputacion\DataObjects;

use App\Enums\GravedadSancion;

/**
 * Resultado de un detector de trampas del {@see CheatDetectionService}.
 *
 * Cada detección expresa una conducta fraudulenta identificada y la sanción
 * proporcional sugerida para ella.
 */
final class Deteccion
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $motivo,
        public readonly GravedadSancion $gravedad,
        public readonly string $mensaje,
        public readonly ?int $reporte_id = null,
        public readonly array $metadata = [],
    ) {}
}

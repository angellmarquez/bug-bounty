<?php

namespace App\Enums;

enum TipoEventoReporte: string
{
    case Creado = 'creado';
    case Enviado = 'enviado';
    case CambioDeEstado = 'cambio_estado';
    case Comentario = 'comentario';
    case MarcadoDuplicado = 'marcado_duplicado';
    case Sancion = 'sancion';
    case Asignacion = 'asignacion';
    // Asignación, pago y verificación en la blockchain de la recompensa (bounty).
    case Bounty = 'bounty';
    // El moderador corrigió el vector CVSS durante el triaje (datos: anterior y nuevo).
    case CvssAjustado = 'cvss_ajustado';

    /** ¿Genera avisos en la campana? Los demás (creado, sanción, bounty) solo quedan en la línea de tiempo. */
    public function generaAviso(): bool
    {
        return match ($this) {
            self::Enviado, self::CambioDeEstado, self::MarcadoDuplicado, self::Asignacion, self::Comentario, self::CvssAjustado => true,
            self::Creado, self::Sancion, self::Bounty => false,
        };
    }
}

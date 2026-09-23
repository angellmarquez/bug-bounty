<?php

namespace App\Enums;

enum EstadoReporte: string
{
    case Borrador = 'borrador';
    case Enviado = 'enviado';
    case EnRevision = 'en_revision';
    case NeedsInfo = 'needs_info';
    case Duplicado = 'duplicado';
    case FueraDeAlcance = 'fuera_de_alcance';
    case Validado = 'validado';
    case EnReparacion = 'en_reparacion';
    case Rechazado = 'rechazado';
    case Cerrado = 'cerrado';
}

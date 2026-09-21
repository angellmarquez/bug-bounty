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
}

<?php

namespace App\Enums;

enum EstadoPrograma: string
{
    case Borrador = 'borrador';
    case Activo = 'activo';
    case EnPausa = 'en_pausa';
    case Archivado = 'archivado';
}

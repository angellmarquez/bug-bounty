<?php

namespace App\Enums;

enum Severidad: string
{
    case Ninguna = 'ninguna';
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case Critica = 'critica';
}

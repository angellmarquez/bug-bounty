<?php

namespace App\Enums;

enum TipoObjetivo: string
{
    case Web = 'web';
    case Api = 'api';
    case Movil = 'movil';
    case Otro = 'otro';
}

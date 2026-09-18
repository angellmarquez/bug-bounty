<?php

namespace App\Enums;

enum EstadoSancion: string
{
    case Aplicada = 'aplicada';
    case Apelada = 'apelada';
    case Revocada = 'revocada';
}

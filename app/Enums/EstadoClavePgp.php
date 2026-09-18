<?php

namespace App\Enums;

enum EstadoClavePgp: string
{
    case PendienteVerificacion = 'pendiente_verificacion';
    case Activa = 'activa';
    case Expirada = 'expirada';
    case Revocada = 'revocada';
}

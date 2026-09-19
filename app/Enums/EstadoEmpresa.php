<?php

namespace App\Enums;

enum EstadoEmpresa: string
{
    case Pendiente = 'pendiente';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';
    case Suspendida = 'suspendida';
}
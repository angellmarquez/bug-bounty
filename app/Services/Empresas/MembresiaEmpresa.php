<?php

namespace App\Services\Empresas;

use App\Models\User;

/**
 * Quién forma parte de una empresa.
 *
 * Una empresa la opera solo su propietario (quien la registró, con rol `empresa`).
 * Los investigadores nunca pertenecen a una empresa ni publican programas: a lo sumo
 * se les invita a un programa privado para enviarle informes (`programa_invitados`).
 */
class MembresiaEmpresa
{
    public const PROPIETARIO = 'propietario';

    /** ¿Tiene ya una empresa activa? */
    public function pertenece(User $usuario): bool
    {
        return $usuario->empresas()->wherePivot('estado', 'activo')->exists();
    }
}

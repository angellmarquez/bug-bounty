<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Fila de `empresa_usuario`: pertenencia de un usuario a una empresa.
 *
 * @property int $empresa_id
 * @property int $usuario_id
 * @property string $rol_interno `propietario`
 * @property string $estado
 * @property string|null $invitado_en
 * @property string|null $aceptado_en
 */
class MiembroEmpresa extends Pivot
{
    protected $table = 'empresa_usuario';
}

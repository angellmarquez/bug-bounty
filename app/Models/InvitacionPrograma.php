<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Fila de `programa_invitados`: invitación de un investigador a un programa privado.
 *
 * @property int $programa_id
 * @property int $investigador_id
 * @property int $invitado_por
 * @property string $estado pendiente, aceptada, rechazada o cancelada
 * @property-read User $investigador
 */
class InvitacionPrograma extends Pivot
{
    protected $table = 'programa_invitados';

    /**
     * @return BelongsTo<User, $this>
     */
    public function investigador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigador_id');
    }
}

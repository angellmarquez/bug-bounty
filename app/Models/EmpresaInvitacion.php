<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $empresa_id
 * @property string $email
 * @property string $token
 * @property string $rol_interno
 * @property string $estado
 * @property int $invitado_por
 * @property Carbon $expira_en
 * @property Carbon|null $aceptado_en
 */
#[Fillable(['empresa_id', 'usuario_id', 'email', 'token', 'rol_interno', 'estado', 'invitado_por', 'expira_en', 'aceptado_en', 'respondida_en'])]
class EmpresaInvitacion extends Model
{
    protected $table = 'empresa_invitaciones';

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<User, $this> */
    public function invitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitado_por');
    }

    /** @return BelongsTo<User, $this> El usuario invitado. */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'aceptado_en' => 'datetime',
            'respondida_en' => 'datetime',
        ];
    }
}

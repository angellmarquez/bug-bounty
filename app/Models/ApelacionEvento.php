<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un paso del ciclo de vida de una apelación (presentada, aprobada o rechazada).
 * Es un registro inmutable: cada paso lleva la huella del anterior (ver TrazaApelaciones).
 *
 * @property int $id
 * @property int $apelacion_id
 * @property string $tipo
 * @property int|null $actor_id
 * @property string|null $actor_nombre
 * @property string|null $actor_rol
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $nota
 * @property array<string, mixed>|null $datos
 * @property string|null $huella_anterior
 * @property string $huella
 */
#[Fillable(['apelacion_id', 'tipo', 'actor_id', 'actor_nombre', 'actor_rol', 'ip', 'user_agent', 'nota', 'datos', 'huella_anterior', 'huella', 'created_at'])]
class ApelacionEvento extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'apelacion_eventos';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Apelacion, $this> */
    public function apelacion(): BelongsTo
    {
        return $this->belongsTo(Apelacion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

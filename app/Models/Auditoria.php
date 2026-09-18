<?php

namespace App\Models;

use Database\Factories\AuditoriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $usuario_id
 * @property string $accion
 * @property string|null $entidad_type
 * @property int|null $entidad_id
 * @property array<int|string, mixed>|null $detalle
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property-read User|null $usuario
 * @property-read Model|null $entidad
 */
#[Fillable(['usuario_id', 'accion', 'entidad_type', 'entidad_id', 'detalle', 'ip', 'user_agent'])]
class Auditoria extends Model
{
    /** @use HasFactory<AuditoriaFactory> */
    use HasFactory;

    /**
     * La tabla es un registro inmutable: no se mantiene updated_at.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'detalle' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * El usuario que ejecutó la acción (nullable).
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * La entidad auditada (polimórfica, nullable).
     *
     * @return MorphTo<Model, $this>
     */
    public function entidad(): MorphTo
    {
        return $this->morphTo();
    }
}

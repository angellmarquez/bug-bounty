<?php

namespace App\Models;

use Database\Factories\AuditoriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RuntimeException;

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
     * El registro de auditoría es inmutable por diseño: solo admite altas,
     * nunca modificaciones ni borrados.
     */
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new RuntimeException('El registro de auditoría es inmutable: no se pueden actualizar entradas.'));
        static::deleting(fn (): never => throw new RuntimeException('El registro de auditoría es inmutable: no se pueden eliminar entradas.'));
    }

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

    /**
     * Punto único para dejar constancia de una acción: usuario, ip y user agent
     * salen de la request actual salvo que se pasen explícitos (para trabajos en
     * cola o comandos, donde no hay request). `$entidad->getMorphClass()` usa el
     * alias corto registrado en `AppServiceProvider` (ver `Relation::morphMap`).
     *
     * @param  array<int|string, mixed>  $detalle
     */
    public static function registrar(
        string $accion,
        ?Model $entidad = null,
        array $detalle = [],
        int|string|null $usuarioId = null,
    ): self {
        return static::query()->create([
            'usuario_id' => $usuarioId ?? auth()->id(),
            'accion' => $accion,
            'entidad_type' => $entidad?->getMorphClass(),
            'entidad_id' => $entidad?->getKey(),
            'detalle' => $detalle === [] ? null : $detalle,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}

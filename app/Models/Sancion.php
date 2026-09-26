<?php

namespace App\Models;

use App\Enums\EstadoApelacion;
use App\Enums\EstadoSancion;
use App\Enums\GravedadSancion;
use Database\Factories\SancionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $usuario_id
 * @property int|null $reporte_id
 * @property string $motivo
 * @property GravedadSancion $gravedad
 * @property int $puntos
 * @property EstadoSancion $estado
 * @property Carbon|null $suspension_desde
 * @property Carbon|null $suspension_hasta
 * @property Carbon|null $plazo_apelacion
 * @property array<int|string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $usuario
 * @property-read Reporte|null $reporte
 * @property-read Collection<int, Apelacion> $apelaciones
 * @property-read Collection<int, EntradaReputacion> $entradas
 */
#[Fillable(['usuario_id', 'reporte_id', 'motivo', 'gravedad', 'puntos', 'estado', 'suspension_desde', 'suspension_hasta', 'plazo_apelacion', 'metadata', 'aplicada_por'])]
class Sancion extends Model
{
    /** @use HasFactory<SancionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'sanciones';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gravedad' => GravedadSancion::class,
            'estado' => EstadoSancion::class,
            'puntos' => 'integer',
            'metadata' => 'array',
            'suspension_desde' => 'datetime',
            'suspension_hasta' => 'datetime',
            'plazo_apelacion' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * El usuario sancionado.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * El reporte que motivó la sanción (nullable).
     *
     * @return BelongsTo<Reporte, $this>
     */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    /**
     * Quién aplicó la sanción; null cuando la aplicó el sistema (auditoría automática).
     *
     * @return BelongsTo<User, $this>
     */
    public function aplicadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aplicada_por');
    }

    /**
     * Las apelaciones presentadas contra la sanción.
     *
     * @return HasMany<Apelacion, $this>
     */
    public function apelaciones(): HasMany
    {
        return $this->hasMany(Apelacion::class);
    }

    /**
     * Las entradas negativas del ledger generadas por la sanción.
     *
     * @return HasMany<EntradaReputacion, $this>
     */
    public function entradas(): HasMany
    {
        return $this->hasMany(EntradaReputacion::class);
    }

    /**
     * Filtra las sanciones vigentes (no revocadas).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereIn('estado', [EstadoSancion::Aplicada, EstadoSancion::Apelada]);
    }

    /**
     * Sanciones vigentes cuya suspensión está en curso ahora mismo.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSuspensionEnCurso(Builder $query): Builder
    {
        return $query->vigentes()
            ->where('suspension_desde', '<=', now())
            ->where('suspension_hasta', '>', now())
            // Pausa cautelar: durante una apelación pendiente no se mantiene la suspensión activa
            ->whereDoesntHave('apelaciones', fn (Builder $q) => $q->where('estado', EstadoApelacion::Pendiente));
    }
}

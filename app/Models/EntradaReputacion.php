<?php

namespace App\Models;

use Database\Factories\EntradaReputacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $usuario_id
 * @property int $puntos
 * @property string $motivo
 * @property int|null $reporte_id
 * @property int|null $sancion_id
 * @property int|null $apelacion_id
 * @property array<int|string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property-read User $usuario
 * @property-read Reporte|null $reporte
 * @property-read Sancion|null $sancion
 * @property-read Apelacion|null $apelacion
 */
#[Fillable(['usuario_id', 'puntos', 'motivo', 'reporte_id', 'sancion_id', 'apelacion_id', 'metadata'])]
class EntradaReputacion extends Model
{
    /** @use HasFactory<EntradaReputacionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ledger_reputacion';

    /**
     * La tabla es un ledger inmutable: no se mantiene updated_at.
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
            'puntos' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Whether this entry affects the caller's balance. Negative values penalize.
     */
    public function getEsNegativaAttribute(): bool
    {
        return $this->puntos < 0;
    }

    /**
     * El usuario al que se le asienta la entrada.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * El reporte que originó la entrada (nullable).
     *
     * @return BelongsTo<Reporte, $this>
     */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    /**
     * La sanción que originó la entrada (nullable).
     *
     * @return BelongsTo<Sancion, $this>
     */
    public function sancion(): BelongsTo
    {
        return $this->belongsTo(Sancion::class);
    }

    /**
     * La apelación que originó la entrada (nullable).
     *
     * @return BelongsTo<Apelacion, $this>
     */
    public function apelacion(): BelongsTo
    {
        return $this->belongsTo(Apelacion::class);
    }
}

<?php

namespace App\Models;

use App\Enums\EstadoApelacion;
use Database\Factories\ApelacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sancion_id
 * @property int $usuario_id
 * @property string $motivo
 * @property array<int|string, mixed>|null $evidencia
 * @property EstadoApelacion $estado
 * @property int|null $resuelta_por
 * @property Carbon|null $resuelta_en
 * @property string|null $nota_resolucion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sancion $sancion
 * @property-read User $usuario
 * @property-read User|null $resueltaPor
 */
#[Fillable(['sancion_id', 'usuario_id', 'motivo', 'evidencia', 'estado', 'resuelta_por', 'resuelta_en', 'nota_resolucion'])]
class Apelacion extends Model
{
    /** @use HasFactory<ApelacionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'apelaciones';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evidencia' => 'array',
            'estado' => EstadoApelacion::class,
            'resuelta_en' => 'datetime',
        ];
    }

    /**
     * La sanción que se apela.
     *
     * @return BelongsTo<Sancion, $this>
     */
    public function sancion(): BelongsTo
    {
        return $this->belongsTo(Sancion::class);
    }

    /**
     * Los pasos registrados de la apelación, del primero al último.
     *
     * @return HasMany<ApelacionEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(ApelacionEvento::class)->orderBy('id');
    }

    /**
     * El usuario que presenta la apelación.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * El usuario que resolvió la apelación (nullable).
     *
     * @return BelongsTo<User, $this>
     */
    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    /**
     * Fotos de evidencia (cifradas).
     *
     * @return MorphMany<Adjunto, $this>
     */
    public function adjuntos(): MorphMany
    {
        return $this->morphMany(Adjunto::class, 'adjuntable')->orderBy('id');
    }
}

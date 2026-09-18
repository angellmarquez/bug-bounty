<?php

namespace App\Models;

use App\Enums\EstadoPrograma;
use Database\Factories\ProgramaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string $slug
 * @property string $descripcion
 * @property EstadoPrograma $estado
 * @property string $moneda
 * @property int|float $recompensa_min
 * @property int|float $recompensa_max
 * @property bool $requiere_poc
 * @property bool $es_publico
 * @property int|null $creado_por
 * @property Carbon|null $inicia_en
 * @property Carbon|null $termina_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creador
 * @property-read Collection<int, ObjetivoPrograma> $objetivos
 * @property-read Collection<int, Reporte> $reportes
 */
#[Fillable(['nombre', 'slug', 'descripcion', 'estado', 'recompensa_min', 'recompensa_max', 'moneda', 'requiere_poc', 'es_publico', 'creado_por', 'inicia_en', 'termina_en'])]
class Programa extends Model
{
    /** @use HasFactory<ProgramaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoPrograma::class,
            'recompensa_min' => 'decimal:2',
            'recompensa_max' => 'decimal:2',
            'requiere_poc' => 'boolean',
            'es_publico' => 'boolean',
            'inicia_en' => 'datetime',
            'termina_en' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * El usuario que gestiona y creó el programa.
     *
     * @return BelongsTo<User, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Los objetivos (ámbito) del programa.
     *
     * @return HasMany<ObjetivoPrograma, $this>
     */
    public function objetivos(): HasMany
    {
        return $this->hasMany(ObjetivoPrograma::class);
    }

    /**
     * Los reportes presentados al programa.
     *
     * @return HasMany<Reporte, $this>
     */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
    }
}

<?php

namespace App\Models;

use App\Enums\TipoObjetivo;
use Database\Factories\ObjetivoProgramaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $programa_id
 * @property TipoObjetivo $tipo
 * @property string $valor
 * @property string|null $descripcion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Programa $programa
 */
#[Fillable(['programa_id', 'tipo', 'valor', 'descripcion'])]
class ObjetivoPrograma extends Model
{
    /** @use HasFactory<ObjetivoProgramaFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'objetivos_programa';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoObjetivo::class,
        ];
    }

    /**
     * El programa al que pertenece el objetivo.
     *
     * @return BelongsTo<Programa, $this>
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }
}

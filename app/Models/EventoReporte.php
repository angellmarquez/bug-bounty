<?php

namespace App\Models;

use App\Enums\TipoEventoReporte;
use App\Observers\EventoReporteObserver;
use Database\Factories\EventoReporteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reporte_id
 * @property int|null $actor_id
 * @property TipoEventoReporte $tipo
 * @property string|null $nota
 * @property array<int|string, mixed>|null $datos
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Reporte $reporte
 * @property-read User|null $actor
 */
#[Fillable(['reporte_id', 'actor_id', 'tipo', 'nota', 'datos'])]
#[ObservedBy(EventoReporteObserver::class)]
class EventoReporte extends Model
{
    /** @use HasFactory<EventoReporteFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'eventos_reporte';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoReporte::class,
            'datos' => 'array',
        ];
    }

    /**
     * El reporte al que pertenece el evento.
     *
     * @return BelongsTo<Reporte, $this>
     */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    /**
     * El usuario que generó el evento (nullable).
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

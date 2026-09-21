<?php

namespace App\Models;

use App\Enums\EstadoReporte;
use App\Enums\Severidad;
use Database\Factories\ReporteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
 * @property string $numero_reporte
 * @property int $programa_id
 * @property int $investigador_id
 * @property int|null $asignado_a
 * @property string $titulo
 * @property string $descripcion Bloque PGP armored (o texto en claro legacy).
 * @property string|null $categoria
 * @property string|null $vector_cvss
 * @property int|float|null $puntuacion_cvss
 * @property Severidad|null $severidad
 * @property string|null $poc Bloque PGP armored con el PoC en JSON.
 * @property string|null $clave_huella
 * @property EstadoReporte $estado
 * @property int|float|null $recompensa
 * @property string $moneda
 * @property int|null $es_duplicado_de
 * @property string|null $notas_internas
 * @property Carbon|null $enviado_en
 * @property Carbon|null $cerrado_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Programa $programa
 * @property-read User $investigador
 * @property-read User|null $asignadoA
 * @property-read Collection<int, EventoReporte> $eventos
 * @property-read Reporte|null $duplicadoDe
 * @property-read Collection<int, Reporte> $duplicados
 * @property-read Collection<int, EntradaReputacion> $entradasReputacion
 */
#[Fillable(['numero_reporte', 'programa_id', 'investigador_id', 'asignado_a', 'titulo', 'descripcion', 'categoria', 'vector_cvss', 'puntuacion_cvss', 'severidad', 'poc', 'clave_huella', 'estado', 'recompensa', 'moneda', 'es_duplicado_de', 'notas_internas', 'enviado_en', 'cerrado_en'])]
#[Hidden(['notas_internas'])]
class Reporte extends Model
{
    /** @use HasFactory<ReporteFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Estados que mantienen vivo el caso en el ciclo de triaje.
     */
    public const ESTADOS_ABIERTOS = [
        'borrador',
        'enviado',
        'en_revision',
        'validado',
        'en_reparacion',
        'pago_pendiente',
    ];

    /**
     * Estados a la espera de una decisión del moderador.
     */
    public const ESTADOS_PENDIENTES = ['enviado', 'en_revision'];

    /**
     * Estados que indican que el moderador aprobó (validó) el informe.
     */
    public const ESTADOS_APROBADOS = ['validado', 'en_reparacion', 'pago_pendiente', 'pagado'];

    /**
     * Estados en los que el informe fue descartado.
     */
    public const ESTADOS_RECHAZADOS = ['rechazado', 'duplicado', 'fuera_de_alcance'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'puntuacion_cvss' => 'decimal:1',
            'severidad' => Severidad::class,
            'estado' => EstadoReporte::class,
            'recompensa' => 'decimal:2',
            'enviado_en' => 'datetime',
            'cerrado_en' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * El programa al que se presentó el reporte.
     *
     * @return BelongsTo<Programa, $this>
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    /**
     * El investigador autor del reporte.
     *
     * @return BelongsTo<User, $this>
     */
    public function investigador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigador_id');
    }

    /**
     * El analista asignado al triaje (nullable).
     *
     * @return BelongsTo<User, $this>
     */
    public function asignadoA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    /**
     * Los eventos que conforman la línea de tiempo del reporte.
     *
     * @return HasMany<EventoReporte, $this>
     */
    public function eventos(): HasMany
    {
        // Desempate por id: varios eventos pueden crearse en el mismo segundo.
        return $this->hasMany(EventoReporte::class)->latest()->orderByDesc('id');
    }

    /**
     * El reporte del que este es duplicado (nullable).
     *
     * @return BelongsTo<Reporte, $this>
     */
    public function duplicadoDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'es_duplicado_de');
    }

    /**
     * Los reportes marcados como duplicados de este.
     *
     * @return HasMany<Reporte, $this>
     */
    public function duplicados(): HasMany
    {
        return $this->hasMany(self::class, 'es_duplicado_de');
    }

    /**
     * Las entradas del ledger de reputación vinculadas al reporte.
     *
     * @return HasMany<EntradaReputacion, $this>
     */
    public function entradasReputacion(): HasMany
    {
        return $this->hasMany(EntradaReputacion::class);
    }

    /**
     * Filtra los reportes aún abiertos en el ciclo de triaje.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->whereIn('estado', self::ESTADOS_ABIERTOS);
    }
}

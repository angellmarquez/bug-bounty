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
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property-read Collection<int, Sancion> $sanciones
 * @property-read Reporte|null $duplicadoDe
 * @property-read Collection<int, Reporte> $duplicados
 * @property-read Collection<int, EntradaReputacion> $entradasReputacion
 * @property-read CertificadoDivulgacion|null $certificado
 */
#[Fillable(['numero_reporte', 'programa_id', 'investigador_id', 'asignado_a', 'titulo', 'descripcion', 'categoria', 'vector_cvss', 'puntuacion_cvss', 'severidad', 'poc', 'clave_huella', 'estado', 'es_duplicado_de', 'notas_internas', 'enviado_en', 'cerrado_en'])]
#[Hidden(['notas_internas'])]
class Reporte extends Model
{
    /** @use HasFactory<ReporteFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Cómo ve al autor quien modera el informe (triaje ciego, ver ocultaAutorA()).
     */
    public const AUTOR_ANONIMO = 'Investigador anónimo';

    /**
     * Estados que mantienen vivo el caso en el ciclo de triaje.
     */
    public const ESTADOS_ABIERTOS = [
        'borrador',
        'enviado',
        'en_revision',
        'needs_info',
        'validado',
        'en_reparacion',
    ];

    /**
     * Estados a la espera de una decisión del moderador.
     */
    public const ESTADOS_PENDIENTES = ['enviado', 'en_revision', 'needs_info'];

    /**
     * Lo único que ve la empresa dueña: los informes ya decididos por moderación, aprobados
     * (validado, en reparación, cerrado) o descartados (rechazado, duplicado, fuera de alcance).
     * Nunca un borrador ni uno que todavía se está revisando (enviado, en revisión, needs_info).
     */
    public const ESTADOS_VISIBLES_EMPRESA = ['validado', 'en_reparacion', 'cerrado', 'rechazado', 'duplicado', 'fuera_de_alcance'];

    /**
     * Estados que indican que el moderador aprobó (validó) el informe.
     */
    public const ESTADOS_APROBADOS = ['validado', 'en_reparacion', 'cerrado'];

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
        return $this->belongsTo(Programa::class)->withTrashed();
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
     * Sanciones disciplinarias originadas por este reporte (si las hubiera).
     *
     * @return HasMany<Sancion, $this>
     */
    public function sanciones(): HasMany
    {
        return $this->hasMany(Sancion::class);
    }

    /**
     * Certificado de divulgación responsable emitido para este reporte.
     *
     * @return HasOne<CertificadoDivulgacion, $this>
     */
    public function certificado(): HasOne
    {
        return $this->hasOne(CertificadoDivulgacion::class);
    }

    /**
     * Indica si el informe fue aprobado en el flujo (validado, en reparación, pagado o cerrado).
     */
    public function estaAprobado(): bool
    {
        return in_array($this->estado->value, self::ESTADOS_APROBADOS, true);
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

    /**
     * El siguiente informe de la cola de un programa: el enviado más antiguo que todavía no
     * tomó ningún moderador. El primero en llegar es el primero en revisarse.
     */
    public static function siguienteEnCola(int $programaId): ?self
    {
        return self::query()
            ->where('programa_id', $programaId)
            ->where('estado', EstadoReporte::Enviado->value)
            ->whereNull('asignado_a')
            ->orderByRaw('COALESCE(enviado_en, created_at) asc')
            ->orderBy('id')
            ->first();
    }

    /**
     * ¿Es el que le toca revisar ahora a los moderadores de su programa? Solo ese (y los que
     * cada moderador ya tomó) se puede abrir: los demás esperan su turno sin exponerse.
     */
    public function esSiguienteEnCola(): bool
    {
        if ($this->estado !== EstadoReporte::Enviado || $this->asignado_a !== null) {
            return false;
        }

        return (int) self::siguienteEnCola((int) $this->programa_id)?->id === (int) $this->id;
    }

    /**
     * Triaje ciego: quien modera el programa del informe (sin ser administrador ni su autor)
     * no puede saber quién lo envió. Sí ve su rango y su historial, para ponderar el hallazgo.
     *
     * @param  array<int, int>|null  $idsModerados  los programas que modera el lector, si ya se consultaron
     *                                              (vacío para un administrador): ahorra consultas en listados
     */
    public function ocultaAutorA(?User $lector, ?array $idsModerados = null): bool
    {
        if ($lector === null || (int) $lector->id === (int) $this->investigador_id) {
            return false;
        }

        $idsModerados ??= self::programasEnTriajeCiego($lector);

        return in_array((int) $this->programa_id, $idsModerados, true);
    }

    /**
     * Los programas en los que el lector modera a ciegas: los que modera, salvo que sea administrador.
     *
     * @return array<int, int>
     */
    public static function programasEnTriajeCiego(User $lector): array
    {
        if ($lector->tieneRol('administrador') || ! $lector->tieneRol('moderador')) {
            return [];
        }

        return $lector->idsProgramasModerados();
    }

    /**
     * El autor tal como lo ve el lector: con nombre, o anónimo si el triaje es ciego.
     *
     * @param  array<int, int>|null  $idsModerados
     * @return array{id: int, name: string, reputation_score: int}
     */
    public function autorPara(?User $lector, ?array $idsModerados = null): array
    {
        $investigador = $this->investigador;
        $oculto = $this->ocultaAutorA($lector, $idsModerados);

        return [
            'id' => $oculto ? 0 : (int) $investigador->id,
            'name' => $oculto ? self::AUTOR_ANONIMO : $investigador->name,
            'reputation_score' => (int) ($investigador->reputation_score ?? 0),
        ];
    }
}

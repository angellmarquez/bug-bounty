<?php

namespace App\Models;

use App\Enums\EstadoPrograma;
use Database\Factories\ProgramaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $empresa_id
 * @property string $nombre
 * @property string $slug
 * @property string $descripcion
 * @property string|null $bugs_buscados
 * @property EstadoPrograma $estado
 * @property string $moneda
 * @property int|float $recompensa_min
 * @property int|float $recompensa_max
 * @property bool $requiere_poc
 * @property bool $es_publico
 * @property int $reputacion_minima
 * @property array<string, mixed>|null $poc_schema
 * @property int|null $creado_por
 * @property Carbon|null $inicia_en
 * @property Carbon|null $termina_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creador
 * @property-read Empresa|null $empresa
 * @property-read Collection<int, ObjetivoPrograma> $objetivos
 * @property-read Collection<int, Reporte> $reportes
 * @property-read Collection<int, User> $moderadores
 */
#[Fillable(['nombre', 'slug', 'descripcion', 'bugs_buscados', 'estado', 'recompensa_min', 'recompensa_max', 'moneda', 'requiere_poc', 'es_publico', 'reputacion_minima', 'poc_schema', 'creado_por', 'empresa_id', 'inicia_en', 'termina_en'])]
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
            'reputacion_minima' => 'integer',
            'poc_schema' => 'array',
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
     * Empresa propietaria del programa, cuando fue creado por una cuenta empresarial.
     *
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
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

    /**
     * Moderadores asignados específicamente a este programa.
     *
     * @return BelongsToMany<User, $this>
     */
    public function moderadores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'programa_moderador', 'programa_id', 'usuario_id')
            ->withPivot(['asignado_por'])
            ->withTimestamps();
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Programa $programa) {
            if (empty($programa->slug)) {
                $programa->slug = Str::slug($programa->nombre);
            }
        });

        static::updating(function (Programa $programa) {
            if ($programa->isDirty('nombre') && ! $programa->isDirty('slug')) {
                $programa->slug = Str::slug($programa->nombre);
            }
        });
    }

    /**
     * Programas en estado activo.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', EstadoPrograma::Activo);
    }

    /**
     * Programas marcados como publicos.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublicos(Builder $query): Builder
    {
        return $query->where('es_publico', true);
    }

    /**
     * Programas visibles segun el rol del usuario.
     * - Investigador: solo activos y publicos.
     * - Gestion/Admin: todos (sin filtro de visibilidad).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisiblesPara(Builder $query, User $user): Builder
    {
        $roles = $user->roles->pluck('slug')->toArray();

        if (in_array('administrador', $roles) || in_array('gestion', $roles)) {
            return $query;
        }

        if (in_array('empresa', $roles)) {
            return $query->whereHas('empresa', function (Builder $empresaQuery) use ($user) {
                $empresaQuery->whereHas('usuarios', function (Builder $usuarioQuery) use ($user) {
                    $usuarioQuery->whereKey($user->id)
                        ->where('empresa_usuario.estado', 'activo');
                });
            });
        }

        return $query
            ->activos()
            ->publicos()
            ->where('reputacion_minima', '<=', (int) ($user->reputation_score ?? 0));
    }

    /**
     * Programas gestionables por el usuario (creados por el, o admin).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeGestionablesPor(Builder $query, User $user): Builder
    {
        $roles = $user->roles->pluck('slug')->toArray();

        if (in_array('administrador', $roles)) {
            return $query;
        }

        if (in_array('empresa', $roles)) {
            return $query->whereHas('empresa', function (Builder $empresaQuery) use ($user) {
                $empresaQuery->whereHas('usuarios', function (Builder $usuarioQuery) use ($user) {
                    $usuarioQuery->whereKey($user->id)
                        ->where('empresa_usuario.estado', 'activo');
                });
            });
        }

        return $query->where('creado_por', $user->id);
    }
}

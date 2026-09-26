<?php

namespace App\Models;

use App\Enums\EstadoPrograma;
use App\Enums\NivelAcceso;
use App\Services\Reputacion\Rangos;
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
 * @property bool $es_publico
 * @property NivelAcceso $nivel_acceso
 * @property array<int, array<string, mixed>>|null $poc_schema
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
#[Fillable(['nombre', 'slug', 'descripcion', 'bugs_buscados', 'estado', 'es_publico', 'nivel_acceso', 'poc_schema', 'creado_por', 'empresa_id', 'inicia_en', 'termina_en'])]
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
            'es_publico' => 'boolean',
            'nivel_acceso' => NivelAcceso::class,
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

    /**
     * Investigadores invitados a este programa privado.
     *
     * @return BelongsToMany<User, $this, InvitacionPrograma>
     */
    public function hackersInvitados(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'programa_invitados', 'programa_id', 'investigador_id')
            ->using(InvitacionPrograma::class)
            ->withPivot(['invitado_por', 'estado'])
            ->withTimestamps();
    }

    /**
     * Lista de IDs de investigadores con invitación aceptada ($P.invited_hacker_ids para ABAC).
     *
     * @return array<int, int>
     */
    public function getInvitedHackerIdsAttribute(): array
    {
        return $this->hackersInvitados()
            ->wherePivot('estado', 'aceptada')
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Programa $programa) {
            if (empty($programa->slug)) {
                $programa->slug = static::slugDisponible($programa->nombre);
            }
        });

        static::updating(function (Programa $programa) {
            if ($programa->isDirty('nombre') && ! $programa->isDirty('slug')) {
                $programa->slug = static::slugDisponible($programa->nombre, $programa->id);
            }
        });
    }

    /**
     * Slug único a partir del nombre: añade un sufijo numérico si ya existe,
     * contando también los programas eliminados (soft delete) porque conservan el slug.
     */
    protected static function slugDisponible(string $nombre, ?int $ignorarId = null): string
    {
        $base = Str::slug($nombre) ?: 'programa';
        $slug = $base;
        $sufijo = 2;

        while (
            static::withTrashed()
                ->where('slug', $slug)
                ->when($ignorarId, fn (Builder $query) => $query->where('id', '!=', $ignorarId))
                ->exists()
        ) {
            $slug = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $slug;
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
     * - Admin: todos (sin filtro de visibilidad).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisiblesPara(Builder $query, User $user): Builder
    {
        $roles = $user->roles->pluck('slug')->toArray();

        if (in_array('administrador', $roles)) {
            return $query;
        }

        // Un moderador ve los programas que modera; si además es investigador, también los
        // públicos activos a los que su rango le da acceso.
        if (in_array('moderador', $roles)) {
            $moderados = $user->idsProgramasModerados();
            $esInvestigador = in_array('investigador', $roles);
            $niveles = app(Rangos::class)->nivelesAccesibles((int) ($user->reputation_score ?? 0));

            return $query->where(function (Builder $alcance) use ($moderados, $esInvestigador, $niveles) {
                $alcance->whereIn('id', $moderados);

                if ($esInvestigador) {
                    $alcance->orWhere(fn (Builder $abiertos) => $abiertos->activos()->publicos()->whereIn('nivel_acceso', $niveles));
                }
            });
        }

        if (in_array('empresa', $roles)) {
            return $query->whereHas('empresa', function (Builder $empresaQuery) use ($user) {
                $empresaQuery->whereHas('usuarios', function (Builder $usuarioQuery) use ($user) {
                    $usuarioQuery->whereKey($user->id)
                        ->where('empresa_usuario.estado', 'activo');
                });
            });
        }

        $niveles = app(Rangos::class)->nivelesAccesibles((int) ($user->reputation_score ?? 0));

        // Un investigador ve los programas públicos activos acordes a su rango,
        // Y los programas privados a los que ha sido invitado formalmente.
        return $query->where(function (Builder $alcance) use ($niveles, $user) {
            $alcance->where(fn (Builder $abiertos) => $abiertos->activos()->publicos()->whereIn('nivel_acceso', $niveles))
                ->orWhere(function (Builder $privados) use ($user) {
                    $privados->activos()
                        ->where('es_publico', false)
                        ->whereHas('hackersInvitados', function ($h) use ($user) {
                            $h->whereKey($user->id)->where('programa_invitados.estado', 'aceptada');
                        });
                });
        });
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

        // Ni investigadores ni moderadores gestionan programas.
        return $query->whereRaw('1 = 0');
    }
}

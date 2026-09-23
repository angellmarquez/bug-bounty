<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\EstadoSancion;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int $reputation_score
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Rol> $roles
 * @property-read Collection<int, Empresa> $empresas
 * @property-read Collection<int, Programa> $programas
 * @property-read Collection<int, Reporte> $reportes
 * @property-read Collection<int, Reporte> $reportesAsignados
 * @property-read Collection<int, EventoReporte> $eventos
 * @property-read Collection<int, EntradaReputacion> $entradasReputacion
 * @property-read Collection<int, Sancion> $sanciones
 * @property-read Collection<int, Apelacion> $apelaciones
 * @property-read Collection<int, Auditoria> $auditorias
 */
#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'reputation_score' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Los roles asignados al usuario.
     *
     * @return BelongsToMany<Rol, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_usuario', 'usuario_id', 'rol_id');
    }

    /**
     * Empresas a las que pertenece el usuario.
     *
     * @return BelongsToMany<Empresa, $this>
     */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_usuario', 'usuario_id', 'empresa_id')
            ->withPivot(['rol_interno', 'estado', 'invitado_en', 'aceptado_en'])
            ->withTimestamps();
    }

    /** La empresa a la que pertenece ahora (una sola por usuario), con su rol interno en `pivot`. */
    public function empresaActiva(): ?Empresa
    {
        return $this->empresas()->wherePivot('estado', 'activo')->first();
    }

    public function idEmpresaActiva(): ?int
    {
        return $this->empresaActiva()?->id;
    }

    /** `propietario`, `publicador` o null si no pertenece a ninguna empresa. */
    public function rolEnEmpresa(): ?string
    {
        return $this->empresaActiva()?->pivot->rol_interno;
    }

    /**
     * Los programas que el usuario gestiona.
     *
     * @return HasMany<Programa, $this>
     */
    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class, 'creado_por');
    }

    /**
     * Programas que el usuario puede moderar.
     *
     * @return BelongsToMany<Programa, $this>
     */
    public function programasModerados(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class, 'programa_moderador', 'usuario_id', 'programa_id')
            ->withPivot(['asignado_por'])
            ->withTimestamps();
    }

    /**
     * Programas privados a los que el investigador ha sido invitado.
     *
     * @return BelongsToMany<Programa, $this>
     */
    public function programasInvitados(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class, 'programa_invitados', 'investigador_id', 'programa_id')
            ->withPivot(['invitado_por', 'estado'])
            ->withTimestamps();
    }

    /**
     * Los reportes presentados por el usuario como investigador.
     *
     * @return HasMany<Reporte, $this>
     */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'investigador_id');
    }

    /**
     * Los reportes asignados al usuario para triaje.
     *
     * @return HasMany<Reporte, $this>
     */
    public function reportesAsignados(): HasMany
    {
        return $this->hasMany(Reporte::class, 'asignado_a');
    }

    /**
     * Los eventos de timeline en los que el usuario participó como actor.
     *
     * @return HasMany<EventoReporte, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(EventoReporte::class, 'actor_id');
    }

    /**
     * Las entradas del ledger de reputación del usuario.
     *
     * @return HasMany<EntradaReputacion, $this>
     */
    public function entradasReputacion(): HasMany
    {
        return $this->hasMany(EntradaReputacion::class, 'usuario_id');
    }

    /**
     * Indica si el usuario tiene el rol indicado.
     */
    public function tieneRol(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    /**
     * Ids de los programas que modera. Un moderador solo ve y actúa sobre esos programas.
     *
     * @return array<int, int>
     */
    public function idsProgramasModerados(): array
    {
        return $this->programasModerados()->pluck('programas.id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Un administrador revisa cualquier programa; un moderador, solo los que se le asignaron.
     */
    public function puedeModerarPrograma(Programa|int $programa): bool
    {
        if ($this->tieneRol('administrador')) {
            return true;
        }

        $id = $programa instanceof Programa ? $programa->id : $programa;

        return $this->tieneRol('moderador') && in_array((int) $id, $this->idsProgramasModerados(), true);
    }

    /**
     * Sanción que hoy mantiene suspendido al usuario (no revocada y con suspensión en curso), si la hay.
     */
    public function suspensionActiva(): ?Sancion
    {
        return $this->sanciones()
            ->whereIn('estado', [EstadoSancion::Aplicada->value, EstadoSancion::Apelada->value])
            ->where('suspension_desde', '<=', now())
            ->where('suspension_hasta', '>', now())
            ->latest('suspension_hasta')
            ->first();
    }

    /**
     * Las sanciones aplicadas al usuario.
     *
     * @return HasMany<Sancion, $this>
     */
    public function sanciones(): HasMany
    {
        return $this->hasMany(Sancion::class, 'usuario_id');
    }

    /**
     * Las apelaciones presentadas por el usuario.
     *
     * @return HasMany<Apelacion, $this>
     */
    public function apelaciones(): HasMany
    {
        return $this->hasMany(Apelacion::class, 'usuario_id');
    }

    /**
     * Los registros de auditoría vinculados al usuario.
     *
     * @return HasMany<Auditoria, $this>
     */
    public function auditorias(): HasMany
    {
        return $this->hasMany(Auditoria::class, 'usuario_id');
    }
}

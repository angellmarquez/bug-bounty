<?php

namespace App\Models;

use App\Enums\EstadoEmpresa;
use Database\Factories\EmpresaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $razon_social
 * @property string|null $nombre_comercial
 * @property string $identificador_fiscal
 * @property string $slug
 * @property string $email
 * @property string|null $telefono
 * @property string|null $sitio_web
 * @property EstadoEmpresa $estado
 * @property string|null $motivo_estado
 * @property int|null $aprobado_por
 * @property Carbon|null $aprobado_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, User> $usuarios
 * @property-read User|null $aprobador
 * @property-read Collection<int, EmpresaInvitacion> $invitaciones
 * @property-read ClavePgpEmpresa|null $clavePgp
 */
#[Fillable(['razon_social', 'nombre_comercial', 'identificador_fiscal', 'slug', 'email', 'telefono', 'sitio_web', 'estado', 'motivo_estado', 'aprobado_por', 'aprobado_en'])]
class Empresa extends Model
{
    /** @use HasFactory<EmpresaFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'estado' => EstadoEmpresa::class,
            'aprobado_en' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Usuarios que pertenecen a la empresa.
     *
     * @return BelongsToMany<User, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'empresa_usuario', 'empresa_id', 'usuario_id')
            ->withPivot(['rol_interno', 'estado', 'invitado_en', 'aceptado_en'])
            ->withTimestamps();
    }

    /**
     * Administrador que aprobó o rechazó la empresa.
     *
     * @return BelongsTo<User, $this>
     */
    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /**
     * Programas pertenecientes a esta empresa.
     *
     * @return HasMany<Programa, $this>
     */
    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class);
    }

    /** @return HasMany<EmpresaInvitacion, $this> */
    public function invitaciones(): HasMany
    {
        return $this->hasMany(EmpresaInvitacion::class);
    }

    /**
     * Su clave PGP interna (la genera la plataforma sola, ver PgpService::claveDeEmpresa()).
     *
     * @return HasOne<ClavePgpEmpresa, $this>
     */
    public function clavePgp(): HasOne
    {
        return $this->hasOne(ClavePgpEmpresa::class);
    }
}

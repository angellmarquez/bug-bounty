<?php

namespace App\Models;

use Database\Factories\RolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string $slug
 * @property string|null $descripcion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $usuarios
 */
#[Fillable(['nombre', 'slug', 'descripcion'])]
class Rol extends Model
{
    /** @use HasFactory<RolFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'roles';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * Los usuarios que poseen este rol.
     *
     * @return BelongsToMany<User, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'rol_usuario', 'rol_id', 'usuario_id');
    }
}

<?php

namespace App\Models;

use App\Enums\EstadoClavePgp;
use Database\Factories\ClavePgpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $usuario_id
 * @property string $id_clave
 * @property string $huella
 * @property string $clave_publica
 * @property string|null $algoritmo
 * @property int|null $bits
 * @property Carbon|null $creada_en
 * @property Carbon|null $expira_en
 * @property EstadoClavePgp $estado
 * @property bool $es_principal
 * @property Carbon|null $verificada_en
 * @property Carbon|null $ultimo_uso_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $usuario
 */
#[Fillable(['usuario_id', 'id_clave', 'huella', 'clave_publica', 'algoritmo', 'bits', 'creada_en', 'expira_en', 'estado', 'es_principal', 'verificada_en', 'ultimo_uso_en'])]
class ClavePgp extends Model
{
    /** @use HasFactory<ClavePgpFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'claves_pgp';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoClavePgp::class,
            'es_principal' => 'boolean',
            'bits' => 'integer',
            'creada_en' => 'date',
            'expira_en' => 'datetime',
            'verificada_en' => 'datetime',
            'ultimo_uso_en' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * El usuario propietario de la clave.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}

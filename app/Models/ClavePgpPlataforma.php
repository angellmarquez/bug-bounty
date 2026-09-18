<?php

namespace App\Models;

use Database\Factories\ClavePgpPlataformaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Par de claves PGP interno de la plataforma.
 *
 * @property int $id
 * @property string|null $id_clave
 * @property string $huella
 * @property string $clave_publica
 * @property string $clave_privada
 * @property string $identidad
 * @property string|null $algoritmo
 * @property int|null $bits
 * @property Carbon|null $creada_en
 * @property Carbon|null $expira_en
 * @property bool $activa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'id_clave',
    'huella',
    'clave_publica',
    'clave_privada',
    'identidad',
    'algoritmo',
    'bits',
    'creada_en',
    'expira_en',
    'activa',
])]
#[Hidden(['clave_privada'])]
class ClavePgpPlataforma extends Model
{
    /** @use HasFactory<ClavePgpPlataformaFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'claves_pgp_plataforma';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clave_privada' => 'encrypted',
            'activa' => 'boolean',
            'bits' => 'integer',
            'creada_en' => 'date',
            'expira_en' => 'datetime',
        ];
    }
}

<?php

namespace App\Models;

use App\Casts\CifradoConClavePgp;
use Database\Factories\ClavePgpEmpresaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Par de claves PGP propio de una empresa. La plataforma la genera y
 * custodia sola (PGP interno) la primera vez que la empresa publica un
 * programa -- la empresa no sube ni gestiona nada.
 *
 * @property int $id
 * @property int $empresa_id
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
 * @property-read Empresa $empresa
 */
#[Fillable([
    'empresa_id',
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
class ClavePgpEmpresa extends Model
{
    /** @use HasFactory<ClavePgpEmpresaFactory> */
    use HasFactory;

    protected $table = 'claves_pgp_empresa';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clave_privada' => CifradoConClavePgp::class,
            'activa' => 'boolean',
            'bits' => 'integer',
            'creada_en' => 'date',
            'expira_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}

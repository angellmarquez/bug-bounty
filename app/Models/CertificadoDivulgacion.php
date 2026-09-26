<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reporte_id
 * @property string $codigo
 * @property string $huella
 * @property string $firma_pgp
 * @property string|null $clave_huella
 * @property array<string, mixed> $datos
 * @property int|null $emitido_por_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Reporte $reporte
 * @property-read User|null $emitidoPor
 */
#[Fillable([
    'reporte_id',
    'codigo',
    'huella',
    'firma_pgp',
    'clave_huella',
    'datos',
    'emitido_por_id',
])]
class CertificadoDivulgacion extends Model
{
    protected $table = 'certificados_divulgacion';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'datos' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Reporte, $this>
     */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por_id');
    }
}

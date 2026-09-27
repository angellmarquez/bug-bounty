<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pago del Plan Profesional en USDC a la tesorería del proyecto.
 *
 * @property int $id
 * @property int $empresa_id
 * @property int|null $usuario_id
 * @property string $plan
 * @property float $monto
 * @property int $dias
 * @property string $red
 * @property string $tx_hash
 * @property string $wallet_destino
 * @property string|null $pagador
 * @property int|null $bloque
 * @property string $estado verificando | confirmado | fallido
 * @property string|null $error
 * @property Carbon|null $periodo_desde
 * @property Carbon|null $periodo_hasta
 * @property Carbon|null $confirmado_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Empresa $empresa
 * @property-read User|null $usuario
 */
#[Fillable(['empresa_id', 'usuario_id', 'plan', 'monto', 'dias', 'red', 'tx_hash', 'wallet_destino', 'pagador', 'bloque', 'estado', 'error', 'periodo_desde', 'periodo_hasta', 'confirmado_en'])]
class PagoSuscripcion extends Model
{
    protected $table = 'pagos_suscripcion';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'float',
            'dias' => 'integer',
            'bloque' => 'integer',
            'periodo_desde' => 'datetime',
            'periodo_hasta' => 'datetime',
            'confirmado_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}

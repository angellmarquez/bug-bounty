<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Fila única con la configuración del Plan Profesional que el admin edita desde el panel:
 * la wallet de tesorería (solo su dirección pública) y el precio. Si no existe, se usan los
 * valores de config/suscripciones.php.
 *
 * @property int $id
 * @property string|null $tesoreria
 * @property float $precio_usdc
 * @property int $dias
 * @property int|null $actualizado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $editor
 */
#[Fillable(['tesoreria', 'precio_usdc', 'dias', 'actualizado_por'])]
class ConfiguracionSuscripcion extends Model
{
    protected $table = 'configuraciones_suscripcion';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_usdc' => 'float',
            'dias' => 'integer',
        ];
    }

    public static function actual(): ?self
    {
        try {
            return self::query()->first();
        } catch (Throwable) {
            return null; // Instalación nueva antes de migrar.
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }
}

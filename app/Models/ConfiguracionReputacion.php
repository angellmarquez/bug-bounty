<?php

namespace App\Models;

use Database\Factories\ConfiguracionReputacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila única con los parámetros del motor de reputación editables desde
 * /admin/config/reputacion. Ver ReputacionServiceProvider: al arrancar, esta
 * fila (si existe) sobrescribe los defaults de `config/reputacion.php`.
 *
 * @property int $id
 * @property int $puntos_inicial
 * @property int $puntos_reporte_validado
 * @property int $puntos_reporte_resuelto
 * @property int $puntos_calidad_documentacion
 * @property int $puntos_participacion
 * @property int $penalizacion_leve
 * @property int $penalizacion_media
 * @property int $penalizacion_grave
 * @property int $suspension_leve_dias
 * @property int $suspension_media_dias
 * @property int $suspension_grave_dias
 * @property int $plazo_apelacion_dias
 */
#[Fillable([
    'puntos_inicial',
    'puntos_reporte_validado',
    'puntos_reporte_resuelto',
    'puntos_calidad_documentacion',
    'puntos_participacion',
    'penalizacion_leve',
    'penalizacion_media',
    'penalizacion_grave',
    'suspension_leve_dias',
    'suspension_media_dias',
    'suspension_grave_dias',
    'plazo_apelacion_dias',
])]
class ConfiguracionReputacion extends Model
{
    /** @use HasFactory<ConfiguracionReputacionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'configuraciones_reputacion';

    protected function casts(): array
    {
        return [
            'puntos_inicial' => 'integer',
            'puntos_reporte_validado' => 'integer',
            'puntos_reporte_resuelto' => 'integer',
            'puntos_calidad_documentacion' => 'integer',
            'puntos_participacion' => 'integer',
            'penalizacion_leve' => 'integer',
            'penalizacion_media' => 'integer',
            'penalizacion_grave' => 'integer',
            'suspension_leve_dias' => 'integer',
            'suspension_media_dias' => 'integer',
            'suspension_grave_dias' => 'integer',
            'plazo_apelacion_dias' => 'integer',
        ];
    }

    /**
     * Sobreescribe los valores del array de config `reputacion` con esta fila,
     * en la misma forma anidada que usa `config/reputacion.php`.
     *
     * @return array<string, mixed>
     */
    public function haciaArrayDeConfig(): array
    {
        return [
            'puntos_inicial' => $this->puntos_inicial,
            'puntos' => [
                'reporte_validado' => $this->puntos_reporte_validado,
                'reporte_resuelto' => $this->puntos_reporte_resuelto,
                'calidad_documentacion' => $this->puntos_calidad_documentacion,
                'participacion' => $this->puntos_participacion,
            ],
            'penalizacion' => [
                'leve' => $this->penalizacion_leve,
                'media' => $this->penalizacion_media,
                'grave' => $this->penalizacion_grave,
            ],
            'suspension' => [
                'leve' => ['dias' => $this->suspension_leve_dias],
                'media' => ['dias' => $this->suspension_media_dias],
                'grave' => ['dias' => $this->suspension_grave_dias],
            ],
            'plazo_apelacion_dias' => $this->plazo_apelacion_dias,
        ];
    }

    /**
     * @param  array<string, mixed>  $validado  con la forma del formulario (ver AdminController::updateConfigReputacion)
     * @return array<string, mixed>
     */
    public static function desdeArrayValidado(array $validado): array
    {
        return [
            'puntos_inicial' => $validado['puntos_inicial'],
            'puntos_reporte_validado' => $validado['puntos']['reporte_validado'],
            'puntos_reporte_resuelto' => $validado['puntos']['reporte_resuelto'],
            'puntos_calidad_documentacion' => $validado['puntos']['calidad_documentacion'],
            'puntos_participacion' => $validado['puntos']['participacion'],
            'penalizacion_leve' => $validado['penalizacion']['leve'],
            'penalizacion_media' => $validado['penalizacion']['media'],
            'penalizacion_grave' => $validado['penalizacion']['grave'],
            'suspension_leve_dias' => $validado['suspension']['leve']['dias'],
            'suspension_media_dias' => $validado['suspension']['media']['dias'],
            'suspension_grave_dias' => $validado['suspension']['grave']['dias'],
            'plazo_apelacion_dias' => $validado['plazo_apelacion_dias'],
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\ConfiguracionReputacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfiguracionReputacion>
 */
class ConfiguracionReputacionFactory extends Factory
{
    protected $model = ConfiguracionReputacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'puntos_inicial' => 0,
            'puntos_reporte_validado' => 50,
            'puntos_reporte_resuelto' => 100,
            'puntos_calidad_documentacion' => 10,
            'puntos_participacion' => 5,
            'penalizacion_leve' => -25,
            'penalizacion_media' => -80,
            'penalizacion_grave' => -250,
            'suspension_leve_dias' => 0,
            'suspension_media_dias' => 7,
            'suspension_grave_dias' => 30,
            'plazo_apelacion_dias' => 7,
        ];
    }
}

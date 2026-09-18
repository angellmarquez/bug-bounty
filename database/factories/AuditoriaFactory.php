<?php

namespace Database\Factories;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auditoria>
 */
class AuditoriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'accion' => fake()->randomElement(['reporte.creado', 'reporte.cambio_estado', 'sancion.aplicada', 'abac.denegado']),
            'entidad_type' => null,
            'entidad_id' => null,
            'detalle' => null,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}

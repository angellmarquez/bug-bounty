<?php

namespace Database\Factories;

use App\Enums\EstadoPrograma;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Programa>
 */
class ProgramaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(3),
            'descripcion' => fake()->paragraph(),
            'estado' => EstadoPrograma::Activo->value,
            'recompensa_min' => fake()->randomElement([0, 50, 100]),
            'recompensa_max' => fake()->numberBetween(1000, 50000),
            'moneda' => 'USD',
            'requiere_poc' => fake()->boolean(),
            'es_publico' => true,
            'reputacion_minima' => 0,
            'creado_por' => User::factory(),
            'inicia_en' => now(),
            'termina_en' => now()->addMonths(6),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\EntradaReputacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntradaReputacion>
 */
class EntradaReputacionFactory extends Factory
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
            'puntos' => fake()->numberBetween(1, 50),
            'motivo' => fake()->randomElement(['reporte_validado', 'participacion', 'calidad_documentacion']),
            'reporte_id' => null,
            'sancion_id' => null,
            'apelacion_id' => null,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the entry penalizes the user.
     */
    public function negativa(): static
    {
        return $this->state(fn (array $attributes) => [
            'puntos' => -fake()->numberBetween(1, 100),
        ]);
    }
}

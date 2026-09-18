<?php

namespace Database\Factories;

use App\Enums\TipoObjetivo;
use App\Models\ObjetivoPrograma;
use App\Models\Programa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObjetivoPrograma>
 */
class ObjetivoProgramaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'programa_id' => Programa::factory(),
            'tipo' => fake()->randomElement(TipoObjetivo::class)->value,
            'valor' => fake()->url(),
            'descripcion' => fake()->sentence(),
        ];
    }
}

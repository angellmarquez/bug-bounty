<?php

namespace Database\Factories;

use App\Enums\TipoEventoReporte;
use App\Models\EventoReporte;
use App\Models\Reporte;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventoReporte>
 */
class EventoReporteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporte_id' => Reporte::factory(),
            'actor_id' => User::factory(),
            'tipo' => fake()->randomElement(TipoEventoReporte::class)->value,
            'nota' => fake()->optional()->sentence(),
            'datos' => null,
        ];
    }
}

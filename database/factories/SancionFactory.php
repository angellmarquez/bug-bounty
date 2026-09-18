<?php

namespace Database\Factories;

use App\Enums\EstadoSancion;
use App\Enums\GravedadSancion;
use App\Models\Reporte;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sancion>
 */
class SancionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gravedad = GravedadSancion::from(fake()->randomElement([
            GravedadSancion::Leve->value,
            GravedadSancion::Media->value,
            GravedadSancion::Grave->value,
        ]));

        return [
            'usuario_id' => User::factory(),
            'reporte_id' => null,
            'motivo' => fake()->randomElement(['falso_positivo', 'reporte_duplicado', 'fabricacion_evidencia']),
            'gravedad' => $gravedad->value,
            'puntos' => -match ($gravedad) {
                GravedadSancion::Leve => fake()->numberBetween(5, 20),
                GravedadSancion::Media => fake()->numberBetween(25, 60),
                GravedadSancion::Grave => fake()->numberBetween(80, 200),
            },
            'estado' => EstadoSancion::Aplicada->value,
            'suspension_desde' => null,
            'suspension_hasta' => null,
            'plazo_apelacion' => now()->addDays(7),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the sanction is linked to a report.
     */
    public function paraReporte(?Reporte $reporte = null): static
    {
        return $this->state(fn (array $attributes) => [
            'reporte_id' => $reporte->id ?? Reporte::factory(),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\EstadoReporte;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reporte>
 */
class ReporteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_reporte' => fake()->unique()->regexify('BB-[0-9]{4}-[0-9]{4}'),
            'programa_id' => Programa::factory(),
            'investigador_id' => User::factory(),
            'asignado_a' => null,
            'titulo' => fake()->sentence(4),
            'descripcion' => fake()->paragraphs(3, true),
            'categoria' => fake()->randomElement(['xss', 'sql_injection', 'rce', 'idor', 'csrf']),
            'vector_cvss' => fake()->randomElement(['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H']),
            'puntuacion_cvss' => fake()->randomElement([4.3, 6.1, 7.5, 9.8]),
            'severidad' => fake()->randomElement(['baja', 'media', 'alta', 'critica']),
            'poc' => null,
            'estado' => EstadoReporte::Enviado->value,
            'recompensa' => null,
            'moneda' => 'USD',
            'es_duplicado_de' => null,
            'notas_internas' => null,
            'enviado_en' => now(),
            'cerrado_en' => null,
        ];
    }

    /**
     * Indicate that the report is still a draft.
     */
    public function borrador(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoReporte::Borrador->value,
            'enviado_en' => null,
        ]);
    }
}

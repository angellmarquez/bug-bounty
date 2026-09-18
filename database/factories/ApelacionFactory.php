<?php

namespace Database\Factories;

use App\Enums\EstadoApelacion;
use App\Models\Apelacion;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Apelacion>
 */
class ApelacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sancion_id' => Sancion::factory(),
            'usuario_id' => User::factory(),
            'motivo' => fake()->paragraph(),
            'evidencia' => null,
            'estado' => EstadoApelacion::Pendiente->value,
            'resuelta_por' => null,
            'resuelta_en' => null,
            'nota_resolucion' => null,
        ];
    }

    /**
     * Indicate that the appeal was approved.
     */
    public function aprobada(?User $resolutor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoApelacion::Aprobada->value,
            'resuelta_por' => $resolutor->id ?? User::factory(),
            'resuelta_en' => now(),
            'nota_resolucion' => fake()->sentence(),
        ]);
    }
}

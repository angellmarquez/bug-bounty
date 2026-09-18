<?php

namespace Database\Factories;

use App\Enums\EstadoClavePgp;
use App\Models\ClavePgp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClavePgp>
 */
class ClavePgpFactory extends Factory
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
            'id_clave' => strtoupper(fake()->unique()->bothify('????####')),
            'huella' => fake()->unique()->regexify('[A-F0-9]{40}'),
            'clave_publica' => "-----BEGIN PGP PUBLIC KEY BLOCK-----\n".fake()->text(320)."\n-----END PGP PUBLIC KEY BLOCK-----",
            'algoritmo' => fake()->randomElement(['RSA', 'EdDSA']),
            'bits' => fake()->randomElement([2048, 3072, 4096]),
            'creada_en' => fake()->date(),
            'expira_en' => fake()->optional()->dateTimeBetween('+1 month', '+5 years'),
            'estado' => EstadoClavePgp::Activa->value,
            'es_principal' => true,
            'verificada_en' => now(),
            'ultimo_uso_en' => null,
        ];
    }

    /**
     * Indicate that the key is pending verification.
     */
    public function pendiente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoClavePgp::PendienteVerificacion->value,
            'verificada_en' => null,
        ]);
    }
}

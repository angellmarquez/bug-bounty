<?php

namespace Database\Factories;

use App\Models\ClavePgpPlataforma;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClavePgpPlataforma>
 */
class ClavePgpPlataformaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $huella = strtoupper(substr(hash('sha1', (string) Str::uuid()), 0, 40));

        return [
            'id_clave' => strtoupper(Str::random(16)),
            'huella' => $huella,
            'clave_publica' => "-----BEGIN FAKE PGP PUBLIC KEY BLOCK-----\nHuella: {$huella}\n-----END FAKE PGP PUBLIC KEY BLOCK-----",
            'clave_privada' => "-----BEGIN FAKE PGP PRIVATE KEY BLOCK-----\nHuella: {$huella}\n-----END FAKE PGP PRIVATE KEY BLOCK-----",
            'identidad' => fake()->company().' <seguridad@localhost>',
            'algoritmo' => fake()->randomElement(['RSA', 'EdDSA']),
            'bits' => fake()->randomElement([256, 4096]),
            'creada_en' => now()->toDateString(),
            'expira_en' => fake()->optional()->dateTimeBetween('+1 month', '+3 years'),
            'activa' => true,
        ];
    }

    /**
     * Indicate that the key is not the active one.
     */
    public function inactiva(): static
    {
        return $this->state(fn () => ['activa' => false]);
    }
}

<?php

namespace Database\Factories;

use App\Models\ClavePgpEmpresa;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClavePgpEmpresa>
 */
class ClavePgpEmpresaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $huella = strtoupper(substr(hash('sha1', (string) Str::uuid()), 0, 40));

        return [
            'empresa_id' => Empresa::factory(),
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
}

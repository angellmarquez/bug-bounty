<?php

namespace Database\Factories;

use App\Enums\EstadoEmpresa;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Empresa> */
class EmpresaFactory extends Factory
{
    public function definition(): array
    {
        $razonSocial = fake()->unique()->company().' S.A.';

        return [
            'razon_social' => $razonSocial,
            'nombre_comercial' => $razonSocial,
            'identificador_fiscal' => fake()->unique()->numerify('RFC##########'),
            'slug' => Str::slug($razonSocial).'-'.fake()->unique()->numberBetween(100, 999),
            'email' => fake()->unique()->companyEmail(),
            'telefono' => fake()->phoneNumber(),
            'sitio_web' => fake()->url(),
            'estado' => EstadoEmpresa::Pendiente,
            'motivo_estado' => null,
            'aprobado_por' => null,
            'aprobado_en' => null,
        ];
    }

    public function aprobada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoEmpresa::Aprobada,
            'aprobado_en' => now(),
        ]);
    }
}

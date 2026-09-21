<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    /**
     * Seed the application's roles.
     */
    public function run(): void
    {
        $roles = [
            'administrador' => ['nombre' => 'Administrador', 'descripcion' => 'Gestiona plataforma, programas y resoluciones finales.'],
            'investigador' => ['nombre' => 'Investigador', 'descripcion' => 'Presenta reportes y gestiona su perfil PGP.'],
            'empresa' => ['nombre' => 'Empresa', 'descripcion' => 'Gestiona sus programas y recibe reportes de vulnerabilidades.'],
            'moderador' => ['nombre' => 'Moderador', 'descripcion' => 'Revisa y tría los informes de los programas asignados y resuelve apelaciones.'],
        ];

        foreach ($roles as $slug => $datos) {
            Rol::updateOrCreate(
                ['slug' => $slug],
                ['nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion']],
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Programa;
use App\Models\Rol;
use App\Models\User;
use App\Services\Pgp\PgpService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class EmpresaDemoSeeder extends Seeder
{
    public function run(PgpService $pgp): void
    {
        $administrador = User::updateOrCreate(
            ['email' => 'admin@bugbounty.local'],
            ['name' => 'Administrador Demo', 'password' => 'admin', 'email_verified_at' => now()],
        );
        $administrador->roles()->syncWithoutDetaching([Rol::where('slug', 'administrador')->firstOrFail()->id]);

        $moderador = User::updateOrCreate(
            ['email' => 'moderador@bugbounty.local'],
            ['name' => 'Moderador Demo', 'password' => 'moderador', 'email_verified_at' => now()],
        );
        $moderador->roles()->syncWithoutDetaching([Rol::where('slug', 'moderador')->firstOrFail()->id]);

        $investigador = User::updateOrCreate(
            ['email' => 'investigador@bugbounty.local'],
            ['name' => 'Investigador Demo', 'password' => 'investigador', 'email_verified_at' => now()],
        );
        $investigador->roles()->syncWithoutDetaching([Rol::where('slug', 'investigador')->firstOrFail()->id]);

        $propietario = User::updateOrCreate(
            ['email' => 'empresa@bugbounty.local'],
            ['name' => 'Propietario Demo', 'password' => 'empresa', 'email_verified_at' => now()],
        );
        $propietario->roles()->syncWithoutDetaching([Rol::where('slug', 'empresa')->firstOrFail()->id]);

        $empresa = Empresa::updateOrCreate(
            ['identificador_fiscal' => 'DEMO-BB-001'],
            [
                'razon_social' => 'Empresa Demo Seguridad S.A.',
                'nombre_comercial' => 'Empresa Demo',
                'slug' => 'empresa-demo',
                'email' => 'contacto@bugbounty.local',
                'estado' => 'aprobada',
                'aprobado_por' => $administrador->id,
                'aprobado_en' => now(),
            ],
        );
        $empresa->usuarios()->syncWithoutDetaching([
            $propietario->id => ['rol_interno' => 'propietario', 'estado' => 'activo', 'aceptado_en' => now()],
        ]);

        $programa = Programa::firstOrCreate(
            ['slug' => 'empresa-demo-programa'],
            [
                'nombre' => 'Empresa Demo Programa',
                // Cifrada como en ProgramaController (clave de la empresa + custodia).
                ...Arr::only($pgp->cifrarPrograma('Programa de demostración para probar el flujo empresarial.', null, $empresa), ['descripcion']),
                'estado' => 'activo',
                'es_publico' => true,
                'nivel_acceso' => 'bajo',
                'empresa_id' => $empresa->id,
                'creado_por' => $propietario->id,
            ],
        );

        $programa->moderadores()->syncWithoutDetaching([
            $moderador->id => ['asignado_por' => $administrador->id],
        ]);

        $empresaPendiente = Empresa::firstOrCreate(
            ['identificador_fiscal' => 'DEMO-BB-002'],
            [
                'razon_social' => 'Empresa Pendiente Demo S.A.',
                'nombre_comercial' => 'Empresa Pendiente',
                'slug' => 'empresa-pendiente-demo',
                'email' => 'pendiente@bugbounty.local',
                'estado' => 'pendiente',
            ],
        );
        $pendiente = User::updateOrCreate(
            ['email' => 'pendiente@bugbounty.local'],
            ['name' => 'Pendiente Demo', 'password' => 'pendiente', 'email_verified_at' => now()],
        );
        $pendiente->roles()->syncWithoutDetaching([Rol::where('slug', 'empresa')->firstOrFail()->id]);
        $empresaPendiente->usuarios()->syncWithoutDetaching([
            $pendiente->id => ['rol_interno' => 'propietario', 'estado' => 'activo'],
        ]);
    }
}

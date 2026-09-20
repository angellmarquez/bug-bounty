<?php

namespace Database\Seeders;

use App\Models\Programa;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProgramaSeeder extends Seeder
{
    public function run(): void
    {
        $gestion = User::whereHas('roles', fn ($q) => $q->where('slug', 'gestion'))->first();

        if (! $gestion) {
            $gestion = User::factory()->create(['name' => 'Gestor Demo']);
            $gestion->roles()->attach(
                Rol::where('slug', 'gestion')->first()
            );
        }

        // Programa 1: BugBounty Corp — activo, publico, con poc_schema
        $bugbounty = Programa::create([
            'nombre' => 'BugBounty Corp',
            'slug' => 'bugbounty-corp',
            'descripcion' => 'Programa de divulgacion responsable para la plataforma principal de BugBounty Corp. Buscamos vulnerabilidades en nuestra aplicacion web, API REST y aplicacion movil.',
            'estado' => 'activo',
            'moneda' => 'USD',
            'recompensa_min' => 50,
            'recompensa_max' => 5000,
            'requiere_poc' => true,
            'es_publico' => true,
            'creado_por' => $gestion->id,
            'inicia_en' => now()->subMonth(),
            'termina_en' => now()->addMonths(6),
            'poc_schema' => [
                ['name' => 'url', 'label' => 'URL afectada', 'type' => 'url', 'required' => true, 'placeholder' => 'https://ejemplo.com/ruta'],
                ['name' => 'pasos', 'label' => 'Pasos para reproducir', 'type' => 'textarea', 'required' => true, 'help' => 'Describe los pasos exactos para reproducir la vulnerabilidad.'],
                ['name' => 'impacto', 'label' => 'Impacto', 'type' => 'select', 'required' => true, 'options' => [['value' => 'bajo', 'label' => 'Bajo'], ['value' => 'medio', 'label' => 'Medio'], ['value' => 'alto', 'label' => 'Alto'], ['value' => 'critico', 'label' => 'Critico']]],
                ['name' => 'evidencia', 'label' => 'Evidencia (screenshots/POC)', 'type' => 'code', 'required' => false, 'help' => 'Adjunta evidencia en texto o codigo.'],
            ],
        ]);

        $bugbounty->objetivos()->create(['tipo' => 'web', 'valor' => 'https://app.bugbounty-corp.com', 'descripcion' => 'Aplicacion web principal']);
        $bugbounty->objetivos()->create(['tipo' => 'api', 'valor' => 'https://api.bugbounty-corp.com/v1', 'descripcion' => 'API REST v1']);

        // Programa 2: GovSecure — activo, publico, requiere_poc
        $govsecure = Programa::create([
            'nombre' => 'GovSecure',
            'slug' => 'govsecure',
            'descripcion' => 'Plataforma de seguridad gubernamental. Reporta vulnerabilidades en nuestros sistemas de identidad digital y servicios publicos en linea.',
            'estado' => 'activo',
            'moneda' => 'USD',
            'recompensa_min' => 100,
            'recompensa_max' => 10000,
            'requiere_poc' => true,
            'es_publico' => true,
            'creado_por' => $gestion->id,
            'inicia_en' => now()->subWeeks(2),
            'termina_en' => now()->addMonths(12),
        ]);

        $govsecure->objetivos()->create(['tipo' => 'web', 'valor' => 'https://portal.govsecure.gob', 'descripcion' => 'Portal ciudadano']);
        $govsecure->objetivos()->create(['tipo' => 'api', 'valor' => 'https://api.govsecure.gob/v2', 'descripcion' => 'API de servicios']);

        // Programa 3: StartupApp — en_pausa, publico
        $startup = Programa::create([
            'nombre' => 'StartupApp',
            'slug' => 'startup-app',
            'descripcion' => 'Aplicacion SaaS para gestión de proyectos. Actualmente en pausa por rediseño de infraestructura.',
            'estado' => 'en_pausa',
            'moneda' => 'USD',
            'recompensa_min' => 25,
            'recompensa_max' => 2000,
            'requiere_poc' => false,
            'es_publico' => true,
            'creado_por' => $gestion->id,
            'inicia_en' => now()->subMonths(3),
            'termina_en' => now()->addMonth(),
        ]);

        $startup->objetivos()->create(['tipo' => 'web', 'valor' => 'https://app.startupapp.io', 'descripcion' => 'Aplicacion web SaaS']);
    }
}

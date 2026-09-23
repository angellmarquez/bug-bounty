<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Programa;
use App\Models\User;
use App\Services\Pgp\PgpService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class ProgramaSeeder extends Seeder
{
    public function run(PgpService $pgp): void
    {
        // Los programas de demostración son de la empresa demo (los crea EmpresaDemoSeeder antes).
        $empresa = Empresa::where('slug', 'empresa-demo')->firstOrFail();
        $propietario = User::where('email', 'empresa@bugbounty.local')->firstOrFail();

        // Igual que ProgramaController: el alcance (descripción, bugs buscados y objetivos) se guarda
        // cifrado con la clave de la empresa + la de custodia. Sin esto la demo dejaba todo en claro.
        $alcance = fn (string $descripcion, ?string $bugsBuscados = null): array => Arr::only(
            $pgp->cifrarPrograma($descripcion, $bugsBuscados, $empresa),
            ['descripcion', 'bugs_buscados'],
        );
        $objetivo = fn (string $tipo, string $valor, ?string $descripcion = null): array => ['tipo' => $tipo, ...$pgp->cifrarObjetivo($valor, $descripcion, $empresa)];

        // Programa 1: BugBounty Corp — activo, publico, con poc_schema
        $bugbounty = Programa::create([
            'nombre' => 'BugBounty Corp',
            'slug' => 'bugbounty-corp',
            ...$alcance(
                'Programa de divulgacion responsable para la plataforma principal de BugBounty Corp. Buscamos vulnerabilidades en nuestra aplicacion web, API REST y aplicacion movil.',
                'Inyeccion SQL, IDOR en la API de pedidos y escalada de privilegios en el panel de clientes.',
            ),
            'estado' => 'activo',
            'es_publico' => true,
            'creado_por' => $propietario->id,
            'empresa_id' => $empresa->id,
            'inicia_en' => now()->subMonth(),
            'termina_en' => now()->addMonths(6),
            'poc_schema' => [
                ['name' => 'url', 'label' => 'URL afectada', 'type' => 'url', 'required' => true, 'placeholder' => 'https://ejemplo.com/ruta'],
                ['name' => 'pasos', 'label' => 'Pasos para reproducir', 'type' => 'textarea', 'required' => true, 'help' => 'Describe los pasos exactos para reproducir la vulnerabilidad.'],
                ['name' => 'impacto', 'label' => 'Impacto', 'type' => 'select', 'required' => true, 'options' => [['value' => 'bajo', 'label' => 'Bajo'], ['value' => 'medio', 'label' => 'Medio'], ['value' => 'alto', 'label' => 'Alto'], ['value' => 'critico', 'label' => 'Critico']]],
                ['name' => 'evidencia', 'label' => 'Evidencia (screenshots/POC)', 'type' => 'code', 'required' => false, 'help' => 'Adjunta evidencia en texto o codigo.'],
            ],
        ]);

        $bugbounty->objetivos()->create($objetivo('web', 'https://app.bugbounty-corp.com', 'Aplicacion web principal'));
        $bugbounty->objetivos()->create($objetivo('api', 'https://api.bugbounty-corp.com/v1', 'API REST v1'));

        // Programa 2: GovSecure — activo, publico
        $govsecure = Programa::create([
            'nombre' => 'GovSecure',
            'slug' => 'govsecure',
            ...$alcance(
                'Plataforma de seguridad gubernamental. Reporta vulnerabilidades en nuestros sistemas de identidad digital y servicios publicos en linea.',
                'Suplantacion de identidad en el login ciudadano y exposicion de datos personales.',
            ),
            'estado' => 'activo',
            'es_publico' => true,
            'creado_por' => $propietario->id,
            'empresa_id' => $empresa->id,
            'inicia_en' => now()->subWeeks(2),
            'termina_en' => now()->addMonths(12),
        ]);

        $govsecure->objetivos()->create($objetivo('web', 'https://portal.govsecure.gob', 'Portal ciudadano'));
        $govsecure->objetivos()->create($objetivo('api', 'https://api.govsecure.gob/v2', 'API de servicios'));

        // Programa 3: StartupApp — en_pausa, publico
        $startup = Programa::create([
            'nombre' => 'StartupApp',
            'slug' => 'startup-app',
            ...$alcance('Aplicacion SaaS para gestión de proyectos. Actualmente en pausa por rediseño de infraestructura.'),
            'estado' => 'en_pausa',
            'es_publico' => true,
            'creado_por' => $propietario->id,
            'empresa_id' => $empresa->id,
            'inicia_en' => now()->subMonths(3),
            'termina_en' => now()->addMonth(),
        ]);

        $startup->objetivos()->create($objetivo('web', 'https://app.startupapp.io', 'Aplicacion web SaaS'));
    }
}

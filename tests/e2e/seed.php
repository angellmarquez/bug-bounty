<?php

// Datos de ejemplo para las pruebas E2E. Se ejecuta con el entorno de playwright.config.ts
// (base SQLite aislada); nunca contra la base real.

use App\Enums\EstadoPrograma;
use App\Models\Empresa;
use App\Models\ObjetivoPrograma;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\Rol;
use App\Models\User;
use App\Services\Pgp\PgpService;
use Illuminate\Contracts\Console\Kernel;

chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_contains((string) config('database.connections.sqlite.database'), 'e2e')) {
    fwrite(STDERR, "ABORTADO: la base no es la de pruebas E2E.\n");
    exit(1);
}

$roles = [];
foreach (['administrador', 'investigador', 'empresa', 'moderador'] as $slug) {
    $roles[$slug] = Rol::firstOrCreate(['slug' => $slug], ['nombre' => ucfirst($slug), 'descripcion' => 'E2E']);
}

$crear = function (string $nombre, string $email, array $slugs, int $reputacion = 0) use ($roles): User {
    $usuario = User::factory()->create([
        'name' => $nombre,
        'email' => $email,
        'password' => 'password',
        'email_verified_at' => now(),
        'reputation_score' => $reputacion,
    ]);
    $usuario->roles()->sync(collect($slugs)->map(fn ($slug) => $roles[$slug]->id)->all());

    return $usuario;
};

$admin = $crear('Admin E2E', 'admin@e2e.test', ['administrador']);
$moderador = $crear('Moderador E2E', 'moderador@e2e.test', ['moderador']);
$investigador = $crear('Investigador E2E', 'investigador@e2e.test', ['investigador'], 40);
$dobleRol = $crear('Investigador Moderador E2E', 'doble@e2e.test', ['investigador', 'moderador'], 25);
$duenoEmpresa = $crear('Empresa E2E', 'empresa@e2e.test', ['empresa']);

$empresa = Empresa::factory()->aprobada()->create(['razon_social' => 'Acme E2E S.A.', 'nombre_comercial' => 'Acme E2E']);
$empresa->usuarios()->attach($duenoEmpresa, ['rol_interno' => 'propietario', 'estado' => 'activo']);

app(PgpService::class)->generatePlatformKeyPair();

$programa = Programa::factory()->create([
    'empresa_id' => $empresa->id,
    'creado_por' => $duenoEmpresa->id,
    'nombre' => 'Programa Acme E2E',
    'estado' => EstadoPrograma::Activo,
    'es_publico' => true,
    'nivel_acceso' => 'bajo',
    'bugs_buscados' => 'Inyecciones y XSS',
]);
ObjetivoPrograma::factory()->create(['programa_id' => $programa->id, 'tipo' => 'web', 'valor' => 'app.acme.test']);

// Un moderador solo ve los programas que se le asignan (ninguno de los dos reportó en él).
$programa->moderadores()->attach([
    $moderador->id => ['asignado_por' => $admin->id],
    $dobleRol->id => ['asignado_por' => $admin->id],
]);

$borrador =Programa::factory()->create([
    'empresa_id' => $empresa->id,
    'creado_por' => $duenoEmpresa->id,
    'nombre' => 'Programa en borrador E2E',
    'estado' => EstadoPrograma::Borrador,
]);
ObjetivoPrograma::factory()->create(['programa_id' => $borrador->id, 'tipo' => 'api', 'valor' => 'api.acme.test']);

$cifrado = app(PgpService::class)->cifrarReporte('XSS reflejado en el buscador.', ['pasos' => 'Abrir /buscar?q=x']);
foreach (['enviado' => 'Informe enviado E2E', 'validado' => 'Informe validado E2E', 'borrador' => 'Informe borrador E2E'] as $estado => $titulo) {
    $reporte = Reporte::factory()->create([
        'programa_id' => $programa->id,
        'investigador_id' => $investigador->id,
        'titulo' => $titulo,
        'estado' => $estado,
        'enviado_en' => $estado === 'borrador' ? null : now(),
        'descripcion' => $cifrado['descripcion'],
        'poc' => $cifrado['poc'],
        'clave_huella' => $cifrado['clave_huella'],
    ]);
    $reporte->eventos()->create(['actor_id' => $investigador->id, 'tipo' => 'creado', 'nota' => 'Reporte creado como borrador.']);
    $reporte->eventos()->create(['actor_id' => $investigador->id, 'tipo' => 'enviado', 'nota' => 'Reporte enviado para revision.']);
}

echo "E2E: datos de ejemplo listos.\n";

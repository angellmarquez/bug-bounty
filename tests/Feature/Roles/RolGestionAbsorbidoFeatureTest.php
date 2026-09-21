<?php

use App\Models\Programa;
use App\Models\Rol;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/** Ejecuta la migración de datos que absorbe el rol Gestión en Moderador. */
function absorberGestion(): void
{
    (require database_path('migrations/2026_09_23_000001_absorber_rol_gestion_en_moderador.php'))->up();
}

test('los usuarios con el rol gestion pasan a moderador y el rol desaparece', function () {
    $gestion = Rol::create(['slug' => 'gestion', 'nombre' => 'Gestión', 'descripcion' => 'antiguo']);
    $usuario = User::factory()->create();
    $usuario->roles()->attach($gestion);

    absorberGestion();

    expect(Rol::where('slug', 'gestion')->exists())->toBeFalse()
        ->and($usuario->fresh()->roles()->pluck('slug')->all())->toBe(['moderador']);
});

test('quien era gestion e investigador conserva investigador y gana moderador', function () {
    $gestion = Rol::create(['slug' => 'gestion', 'nombre' => 'Gestión', 'descripcion' => 'antiguo']);
    $usuario = conRol(investigador(), 'investigador');
    $usuario->roles()->attach($gestion);

    absorberGestion();

    expect($usuario->fresh()->roles()->pluck('slug')->sort()->values()->all())->toBe(['investigador', 'moderador']);
});

test('quien ya era moderador no queda con el rol duplicado', function () {
    $gestion = Rol::create(['slug' => 'gestion', 'nombre' => 'Gestión', 'descripcion' => 'antiguo']);
    $usuario = moderador();
    $usuario->roles()->attach($gestion);

    absorberGestion();

    expect(DB::table('rol_usuario')->where('usuario_id', $usuario->id)->count())->toBe(1);
});

test('la migracion no hace nada si el rol gestion ya no existe', function () {
    $usuario = investigador();

    absorberGestion();

    expect($usuario->fresh()->roles()->pluck('slug')->all())->toBe(['investigador']);
});

test('los seeders funcionan sin el rol gestion y los programas de demostracion son de la empresa demo', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Rol::pluck('slug')->sort()->values()->all())->toBe(['administrador', 'empresa', 'investigador', 'moderador'])
        ->and(Programa::whereNull('empresa_id')->count())->toBe(0);
});

<?php

use App\Models\Programa;
use App\Models\Reporte;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Helpers ABAC
|--------------------------------------------------------------------------
|
| Utilidades para construir usuarios con roles y objetos del dominio en los
| tests de la matriz ABAC (reutilizadas por tests/Feature/ABAC/).
|
*/

function rol(string $slug): Rol
{
    return Rol::firstOrCreate(
        ['slug' => $slug],
        [
            'nombre' => match ($slug) {
                'administrador' => 'Administrador',
                'gestion' => 'Gestión',
                'investigador' => 'Investigador',
                default => ucfirst($slug),
            },
            'descripcion' => 'Rol de prueba',
        ],
    );
}

function conRol(User $usuario, string|array $slugs): User
{
    $roles = collect((array) $slugs)->map(fn (string $slug) => rol($slug));

    $usuario->roles()->sync($roles->pluck('id'));

    return $usuario;
}

function investigador(array $atributos = []): User
{
    return conRol(User::factory()->create($atributos), 'investigador');
}

function gestion(array $atributos = []): User
{
    return conRol(User::factory()->create($atributos), 'gestion');
}

function administrador(array $atributos = []): User
{
    return conRol(User::factory()->create($atributos), 'administrador');
}

function programaDe(User $usuario, array $atributos = []): Programa
{
    return Programa::factory()->create(['creado_por' => $usuario->id, ...$atributos]);
}

function reporteDe(User $investigador, ?Programa $programa = null, array $atributos = []): Reporte
{
    return Reporte::factory()->create([
        'investigador_id' => $investigador->id,
        'programa_id' => $programa?->id ?? Programa::factory(),
        ...$atributos,
    ]);
}

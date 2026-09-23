<?php

use App\Models\Empresa;
use App\Models\ObjetivoPrograma;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\Rol;
use App\Models\User;
use App\Services\Pgp\PgpService;
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

// El contenido de los reportes se cifra con la clave PGP activa de la plataforma.
// asegurarClave reutiliza la clave existente en vez de regenerar un par completo en cada test.
pest()->beforeEach(fn () => app(PgpService::class)->asegurarClave())
    ->in('Feature/Reportes', 'Feature/Reputacion');

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

function moderador(array $atributos = []): User
{
    return conRol(User::factory()->create($atributos), 'moderador');
}

/**
 * Propietario (quien creó la empresa) de una empresa aprobada nueva: puede crear, editar,
 * publicar y borrar sus programas y ve los informes que reciben.
 */
function propietarioDeEmpresa(array $atributos = []): User
{
    $empresa = Empresa::factory()->aprobada()->create();
    $usuario = conRol(User::factory()->create($atributos), 'empresa');
    $empresa->usuarios()->attach($usuario, ['rol_interno' => 'propietario', 'estado' => 'activo']);

    return $usuario;
}

/**
 * Programa de la empresa del propietario indicado, creado por él.
 */
function programaDeEmpresa(User $propietario, array $atributos = []): Programa
{
    $empresa = $propietario->empresas()->firstOrFail();

    return Programa::factory()->create([
        'creado_por' => $propietario->id,
        'empresa_id' => $empresa->id,
        ...$atributos,
    ]);
}

/**
 * El propietario (rol empresa) de la empresa indicada: quien ve sus informes y gestiona sus programas.
 * Los demás miembros son publicadores (ver publicadorDeEmpresa) y no ven los informes.
 */
function miembroDeEmpresa(Empresa $empresa): User
{
    $usuario = conRol(User::factory()->create(), 'empresa');
    $empresa->usuarios()->attach($usuario, ['rol_interno' => 'propietario', 'estado' => 'activo']);

    return $usuario;
}

/**
 * Un investigador invitado que ya forma parte de la empresa como publicador.
 */
function publicadorDeEmpresa(Empresa $empresa, array $atributos = []): User
{
    $usuario = investigador($atributos);
    $empresa->usuarios()->attach($usuario, ['rol_interno' => 'publicador', 'estado' => 'activo', 'aceptado_en' => now()]);

    return $usuario;
}

/**
 * Moderador de un programa concreto: un moderador solo ve y revisa los programas que se le asignan.
 */
function moderadorDe(Programa|Reporte $alcance, array $atributos = []): User
{
    $moderador = moderador($atributos);
    $moderador->programasModerados()->attach($alcance instanceof Reporte ? $alcance->programa_id : $alcance->id);

    return $moderador;
}

function administrador(array $atributos = []): User
{
    return conRol(User::factory()->create($atributos), 'administrador');
}

/**
 * Descifra el PoC de un reporte (se guarda cifrado con la clave PGP de la plataforma).
 *
 * @return array<int|string, mixed>|null
 */
function pocDe(Reporte $reporte): ?array
{
    $poc = $reporte->getRawOriginal('poc');

    return $poc === null ? null : app(PgpService::class)->descifrarReporte('', $poc)['poc'];
}

/**
 * PoC cifrado, tal como queda guardado en la base de datos.
 *
 * @param  array<int|string, mixed>  $poc
 */
function pocCifrado(array $poc): ?string
{
    return app(PgpService::class)->cifrarReporte('x', $poc)['poc'];
}

/**
 * Un programa solo se puede publicar si define al menos un objetivo.
 */
function conObjetivo(Programa $programa): Programa
{
    ObjetivoPrograma::factory()->create(['programa_id' => $programa->id]);

    return $programa;
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

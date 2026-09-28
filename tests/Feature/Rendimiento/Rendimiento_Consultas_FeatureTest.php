<?php

use App\Abac\AccionesAbac;
use App\Enums\EstadoPrograma;
use App\Models\Auditoria;
use App\Models\Programa;
use App\Models\Sancion;
use App\Services\Pgp\PgpService;
use App\Support\CachePorPeticion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => app(PgpService::class)->asegurarClave());

/** Consultas de lectura que ejecuta el callback. */
function lecturasDe(callable $callback): int
{
    $lecturas = 0;
    DB::listen(function ($consulta) use (&$lecturas): void {
        if (str_starts_with(strtolower(ltrim($consulta->sql)), 'select')) {
            $lecturas++;
        }
    });

    $callback();

    return $lecturas;
}

test('evaluar el ABAC varias veces en una petición no repite las consultas del usuario', function () {
    $investigador = investigador();
    $programa = programaDe(investigador(), ['estado' => EstadoPrograma::Activo->value]);
    $this->actingAs($investigador);

    Gate::allows('abac', [AccionesAbac::ReporteCrear, $programa]);
    $repetidas = lecturasDe(function () use ($programa): void {
        for ($i = 0; $i < 5; $i++) {
            Gate::allows('abac', [AccionesAbac::ReporteCrear, $programa]);
        }
    });

    expect($repetidas)->toBe(0);
});

test('una sanción aplicada a mitad de petición cuenta de inmediato', function () {
    $investigador = investigador();
    $programa = programaDe(investigador(), ['estado' => EstadoPrograma::Activo->value]);
    $this->actingAs($investigador);

    expect(Gate::allows('abac', [AccionesAbac::ReporteCrear, $programa]))->toBeTrue();

    Sancion::factory()->create([
        'usuario_id' => $investigador->id,
        'estado' => 'aplicada',
        'suspension_desde' => now()->subDay(),
        'suspension_hasta' => now()->addDays(5),
    ]);

    expect(Gate::allows('abac', [AccionesAbac::ReporteCrear, $programa]))->toBeFalse();
});

test('una suspensión que vence con el tiempo deja de contar sin escribir nada', function () {
    $investigador = investigador();
    $programa = programaDe(investigador(), ['estado' => EstadoPrograma::Activo->value, 'termina_en' => now()->addMonths(2)]);
    Sancion::factory()->create([
        'usuario_id' => $investigador->id,
        'estado' => 'aplicada',
        'suspension_desde' => now()->subDay(),
        'suspension_hasta' => now()->addDays(2),
    ]);
    $this->actingAs($investigador);

    expect(Gate::allows('abac', [AccionesAbac::ReporteCrear, $programa]))->toBeFalse();

    $this->travel(3)->days();

    expect(Gate::allows('abac', [AccionesAbac::ReporteCrear, $programa]))->toBeTrue();
});

test('escribir en bitácoras (auditoría, sesión) no invalida lo recordado; escribir datos sí', function () {
    expect(CachePorPeticion::invalidaCon('insert into "auditorias" ("accion") values (?)'))->toBeFalse()
        ->and(CachePorPeticion::invalidaCon('update "sessions" set "payload" = ?'))->toBeFalse()
        ->and(CachePorPeticion::invalidaCon('insert into "eventos_reporte" ("tipo") values (?)'))->toBeFalse()
        ->and(CachePorPeticion::invalidaCon('insert into "sanciones" ("estado") values (?)'))->toBeTrue()
        ->and(CachePorPeticion::invalidaCon('insert into `rol_usuario` (`rol_id`) values (?)'))->toBeTrue()
        ->and(CachePorPeticion::invalidaCon('update "users" set "reputation_score" = ?'))->toBeTrue()
        ->and(CachePorPeticion::invalidaCon('select * from "users"'))->toBeFalse();

    $calculos = 0;
    $calcular = function () use (&$calculos): int {
        return ++$calculos;
    };

    CachePorPeticion::recordar('prueba', $calcular);
    Auditoria::registrar('prueba.bitacora', null, []);
    CachePorPeticion::recordar('prueba', $calcular);
    expect($calculos)->toBe(1);

    DB::table('users')->where('id', 0)->update(['name' => 'x']);
    CachePorPeticion::recordar('prueba', $calcular);
    expect($calculos)->toBe(2);
});

test('los listados de programas no envían la descripción ni los objetivos cifrados', function () {
    $propietario = propietarioDeEmpresa();
    $programa = conObjetivo(programaDeEmpresa($propietario, ['estado' => EstadoPrograma::Activo->value, 'es_publico' => true]));
    $cifrado = app(PgpService::class)->cifrarPrograma('Descripción secreta', 'Bugs secretos', $propietario->empresas()->first());
    Programa::query()->whereKey($programa->id)->update([
        'descripcion' => $cifrado['descripcion'],
        'bugs_buscados' => $cifrado['bugs_buscados'],
    ]);

    $this->actingAs($propietario)
        ->get('/gestion/programas')
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('programas.data.0.id', $programa->id)
            ->missing('programas.data.0.descripcion')
            ->missing('programas.data.0.bugs_buscados')
            ->missing('programas.data.0.objetivos.0.valor')
            ->where('programas.data.0.objetivos.0.tipo', fn ($tipo) => is_string($tipo)));

    $this->actingAs(investigador())
        ->get('/programas')
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('programas.data.0.id', $programa->id)
            ->missing('programas.data.0.descripcion')
            ->missing('programas.data.0.bugs_buscados'));
});

<?php

use App\Models\Auditoria;
use App\Models\Programa;
use App\Services\Pgp\PgpService;

beforeEach(fn () => app(PgpService::class)->asegurarClave());

/**
 * @return array<string, mixed>
 */
function datosPrograma(array $extra = []): array
{
    return [
        'nombre' => 'Programa con fechas',
        'descripcion' => 'Programa para probar el periodo de vigencia.',
        'objetivos' => [['tipo' => 'web', 'valor' => 'https://app.ejemplo.com']],
        ...$extra,
    ];
}

// ---------------------------------------------------------------------
// Crear y editar
// ---------------------------------------------------------------------

test('no se crea un programa con fechas pasadas', function (string $campo) {
    $this->actingAs(propietarioDeEmpresa())
        ->post(route('programas.store'), datosPrograma([$campo => now()->subDay()->toDateString()]))
        ->assertSessionHasErrors($campo);
})->with(['inicia_en', 'termina_en']);

test('se crea un programa que empieza hoy y termina en el futuro', function () {
    $this->actingAs(propietarioDeEmpresa())
        ->post(route('programas.store'), datosPrograma([
            'inicia_en' => today()->toDateString(),
            'termina_en' => now()->addMonth()->toDateString(),
        ]))
        ->assertSessionHasNoErrors();
});

test('la fecha de fin no puede ser anterior a la de inicio', function () {
    $this->actingAs(propietarioDeEmpresa())
        ->post(route('programas.store'), datosPrograma([
            'inicia_en' => now()->addMonth()->toDateString(),
            'termina_en' => now()->addWeek()->toDateString(),
        ]))
        ->assertSessionHasErrors('termina_en');
});

test('al editar se conserva una fecha de inicio pasada, pero no se puede mover el fin al pasado', function () {
    $duena = propietarioDeEmpresa();
    $programa = conObjetivo(programaDeEmpresa($duena, [
        'inicia_en' => now()->subMonths(2),
        'termina_en' => now()->addMonth(),
    ]));
    $this->actingAs($duena);

    $this->put(route('programas.update', $programa), [
        'nombre' => 'Nombre nuevo',
        'descripcion' => $programa->descripcion,
        'inicia_en' => $programa->inicia_en->toDateString(),
        'termina_en' => $programa->termina_en->toDateString(),
    ])->assertSessionHasNoErrors();

    $this->put(route('programas.update', $programa), [
        'nombre' => 'Nombre nuevo',
        'descripcion' => $programa->descripcion,
        'termina_en' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors('termina_en');
});

test('no se publica un programa cuya fecha de fin ya paso', function () {
    $duena = propietarioDeEmpresa();
    $programa = conObjetivo(programaDeEmpresa($duena, ['estado' => 'borrador', 'termina_en' => now()->subDay()]));

    $this->actingAs($duena)
        ->post(route('programas.cambiar-estado', $programa), ['estado' => 'activo'])
        ->assertSessionHasErrors('estado');

    expect($programa->fresh()->estado->value)->toBe('borrador');
});

// ---------------------------------------------------------------------
// Informes fuera del periodo
// ---------------------------------------------------------------------

test('fuera del periodo del programa no se puede reportar', function (array $fechas, bool $puede) {
    $programa = conObjetivo(Programa::factory()->create(['estado' => 'activo', 'es_publico' => true, ...$fechas]));

    $this->actingAs(investigador())
        ->post(route('reportes.store'), [
            'programa_id' => $programa->id,
            'titulo' => 'Hallazgo de prueba',
            'descripcion' => 'Descripción del hallazgo de prueba.',
        ])
        ->assertStatus($puede ? 302 : 403);
})->with([
    'ya termino' => [fn () => ['termina_en' => now()->subDay()], false],
    'aun no empieza' => [fn () => ['inicia_en' => now()->addDay()], false],
    'termina hoy (vale todo el dia)' => [fn () => ['termina_en' => today()], true],
    'dentro del periodo' => [fn () => ['inicia_en' => now()->subWeek(), 'termina_en' => now()->addWeek()], true],
]);

test('un borrador no se puede enviar si el programa ya termino', function () {
    $programa = Programa::factory()->create(['estado' => 'activo', 'termina_en' => now()->subDay()]);
    $autor = investigador();
    $borrador = reporteDe($autor, $programa, ['estado' => 'borrador']);

    $this->actingAs($autor)->post(route('reportes.enviar', $borrador))->assertForbidden();

    expect($borrador->fresh()->estado->value)->toBe('borrador');
});

test('la pagina del programa no ofrece reportar cuando ya termino', function () {
    $programa = conObjetivo(Programa::factory()->create(['estado' => 'activo', 'es_publico' => true, 'termina_en' => now()->subDay()]));

    $this->actingAs(investigador())
        ->get(route('programas.show', $programa))
        ->assertInertia(fn ($page) => $page->where('puedeReportar', false));
});

// ---------------------------------------------------------------------
// Cierre automatico
// ---------------------------------------------------------------------

test('el scheduler pone en pausa los programas vencidos y avisa a la empresa', function () {
    $duena = propietarioDeEmpresa();
    $vencido = programaDeEmpresa($duena, ['estado' => 'activo', 'termina_en' => now()->subDay()]);
    $terminaHoy = programaDeEmpresa($duena, ['estado' => 'activo', 'termina_en' => today()]);
    $sinFecha = programaDeEmpresa($duena, ['estado' => 'activo', 'termina_en' => null]);

    $this->artisan('programas:pausar-vencidos')->assertSuccessful();

    expect($vencido->fresh()->estado->value)->toBe('en_pausa')
        ->and($terminaHoy->fresh()->estado->value)->toBe('activo')
        ->and($sinFecha->fresh()->estado->value)->toBe('activo')
        ->and(Auditoria::query()->where('accion', 'programas.pausado_por_fecha')->where('entidad_id', $vencido->id)->exists())->toBeTrue()
        ->and($duena->notifications()->get()->pluck('data.titulo'))->toContain('Tu programa llegó a su fecha de fin');
});

test('tras ampliar la fecha, la empresa puede reactivar el programa', function () {
    $duena = propietarioDeEmpresa();
    $programa = conObjetivo(programaDeEmpresa($duena, ['estado' => 'en_pausa', 'termina_en' => now()->subDay()]));
    $this->actingAs($duena);

    $this->post(route('programas.cambiar-estado', $programa), ['estado' => 'activo'])->assertSessionHasErrors('estado');

    $this->put(route('programas.update', $programa), [
        'nombre' => $programa->nombre,
        'descripcion' => $programa->descripcion,
        'termina_en' => now()->addMonth()->toDateString(),
    ])->assertSessionHasNoErrors();
    $this->post(route('programas.cambiar-estado', $programa), ['estado' => 'activo'])->assertSessionHasNoErrors();

    expect($programa->fresh()->estado->value)->toBe('activo');
});

// ---------------------------------------------------------------------
// Limites de longitud
// ---------------------------------------------------------------------

test('el correo de una invitacion tiene limite y formato estricto', function (string $email) {
    $duena = propietarioDeEmpresa();
    $duena->empresas()->firstOrFail()->update(['plan' => 'profesional']);
    $programa = programaDeEmpresa($duena, ['es_publico' => false]);

    $this->actingAs($duena)
        ->post(route('programas.invitaciones.crear', $programa), ['email' => $email])
        ->assertSessionHasErrors('email');
})->with([
    'demasiado largo' => [fn () => str_repeat('a', 250).'@ejemplo.com'],
    'formato invalido' => ['"con comillas"@ejemplo.com'],
]);

test('el valor por defecto de un campo PoC tiene limite', function () {
    $this->actingAs(propietarioDeEmpresa())
        ->post(route('programas.store'), datosPrograma([
            'poc_schema' => [['name' => 'url', 'label' => 'URL', 'type' => 'text', 'defaultValue' => str_repeat('x', 2001)]],
        ]))
        ->assertSessionHasErrors('poc_schema.0.defaultValue');
});

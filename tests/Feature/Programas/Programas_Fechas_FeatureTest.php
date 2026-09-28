<?php

use App\Models\Auditoria;
use App\Models\Programa;
use App\Services\Pgp\PgpService;
use Carbon\CarbonImmutable;

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
        ->post(route('programas.store'), datosPrograma([$campo => now()->subDays(2)->toDateString()]))
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
        'termina_en' => now()->subDays(2)->toDateString(),
    ])->assertSessionHasErrors('termina_en');
});

test('no se publica un programa cuya fecha de fin ya paso', function () {
    $duena = propietarioDeEmpresa();
    $programa = conObjetivo(programaDeEmpresa($duena, ['estado' => 'borrador', 'termina_en' => now()->subDays(2)]));

    $this->actingAs($duena)
        ->post(route('programas.cambiar-estado', $programa), ['estado' => 'activo'])
        ->assertSessionHasErrors('estado');

    expect($programa->fresh()->estado->value)->toBe('borrador');
});

test('para publicar un programa hacen falta fechas y al menos 3 dias abierto', function (array $fechas, bool $puede) {
    $duena = propietarioDeEmpresa();
    $programa = conObjetivo(programaDeEmpresa($duena, ['estado' => 'borrador', 'inicia_en' => null, 'termina_en' => null, ...$fechas]));

    $respuesta = $this->actingAs($duena)->post(route('programas.cambiar-estado', $programa), ['estado' => 'activo']);

    $puede ? $respuesta->assertSessionHasNoErrors() : $respuesta->assertSessionHasErrors('estado');
    expect($programa->fresh()->estado->value)->toBe($puede ? 'activo' : 'borrador');
})->with([
    'sin fechas' => [fn () => [], false],
    'sin fecha de fin' => [fn () => ['inicia_en' => today()], false],
    'sin fecha de inicio' => [fn () => ['termina_en' => today()->addMonth()], false],
    'abierto solo 2 dias' => [fn () => ['inicia_en' => today(), 'termina_en' => today()->addDays(2)], false],
    'ya empezo y le quedan 2 dias' => [fn () => ['inicia_en' => today()->subWeek(), 'termina_en' => today()->addDays(2)], false],
    'abierto 3 dias' => [fn () => ['inicia_en' => today(), 'termina_en' => today()->addDays(3)], true],
]);

test('al crear, la fecha de fin debe quedar al menos 3 dias despues del inicio', function () {
    $this->actingAs(propietarioDeEmpresa())
        ->post(route('programas.store'), datosPrograma([
            'inicia_en' => today()->addDay()->toDateString(),
            'termina_en' => today()->addDays(3)->toDateString(),
        ]))
        ->assertSessionHasErrors('termina_en');
});

test('un programa publicado no puede quedarse sin fechas ni acortarse a menos de 3 dias', function () {
    $duena = propietarioDeEmpresa();
    $programa = conObjetivo(programaDeEmpresa($duena, [
        'estado' => 'activo',
        'inicia_en' => today()->subDays(10),
        'termina_en' => today()->addMonth(),
    ]));
    $this->actingAs($duena);

    $this->put(route('programas.update', $programa), ['termina_en' => ''])
        ->assertSessionHasErrors('termina_en');

    $this->put(route('programas.update', $programa), [
        'inicia_en' => today()->toDateString(),
        'termina_en' => today()->addDay()->toDateString(),
    ])->assertSessionHasErrors('termina_en');
});

test('un programa en curso con pocos dias restantes se sigue pudiendo editar sin tocar sus fechas', function () {
    $duena = propietarioDeEmpresa();
    $programa = conObjetivo(programaDeEmpresa($duena, [
        'estado' => 'activo',
        'inicia_en' => today()->subMonth(),
        'termina_en' => today()->addDay(),
    ]));

    $this->actingAs($duena)->put(route('programas.update', $programa), [
        'nombre' => 'Nombre corregido',
        'inicia_en' => $programa->inicia_en->toDateString(),
        'termina_en' => $programa->termina_en->toDateString(),
    ])->assertSessionHasNoErrors();
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
    'ya termino' => [fn () => ['termina_en' => now()->subDays(2)], false],
    // Pasado mañana: "mañana" ya empezó en UTC+14 desde las 10:00 UTC (ver FechaCalendario).
    'aun no empieza' => [fn () => ['inicia_en' => now()->addDays(2)], false],
    'termina hoy (vale todo el dia)' => [fn () => ['termina_en' => today()], true],
    'dentro del periodo' => [fn () => ['inicia_en' => now()->subWeek(), 'termina_en' => now()->addWeek()], true],
]);

test('un borrador no se puede enviar si el programa ya termino', function () {
    $programa = Programa::factory()->create(['estado' => 'activo', 'termina_en' => now()->subDays(2)]);
    $autor = investigador();
    $borrador = reporteDe($autor, $programa, ['estado' => 'borrador']);

    $this->actingAs($autor)->post(route('reportes.enviar', $borrador))->assertForbidden();

    expect($borrador->fresh()->estado->value)->toBe('borrador');
});

test('la pagina del programa no ofrece reportar cuando ya termino', function () {
    $programa = conObjetivo(Programa::factory()->create(['estado' => 'activo', 'es_publico' => true, 'termina_en' => now()->subDays(2)]));

    $this->actingAs(investigador())
        ->get(route('programas.show', $programa))
        ->assertInertia(fn ($page) => $page->where('puedeReportar', false));
});

// ---------------------------------------------------------------------
// Cierre automatico
// ---------------------------------------------------------------------

test('el scheduler pone en pausa los programas vencidos y avisa a la empresa', function () {
    $duena = propietarioDeEmpresa();
    $vencido = programaDeEmpresa($duena, ['estado' => 'activo', 'termina_en' => now()->subDays(2)]);
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
    $programa = conObjetivo(programaDeEmpresa($duena, ['estado' => 'en_pausa', 'termina_en' => now()->subDays(2)]));
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

// ---------------------------------------------------------------------
// Zonas horarias: la app trabaja en UTC, los usuarios no
// ---------------------------------------------------------------------

test('el "hoy" de un usuario en America no se rechaza aunque en UTC ya sea manana', function () {
    // 00:55 UTC del 28 = 20:55 del 27 en Caracas (UTC-4).
    $this->travelTo(CarbonImmutable::parse('2026-09-28 00:55:00', 'UTC'));

    $this->actingAs(propietarioDeEmpresa())
        ->post(route('programas.store'), datosPrograma([
            'inicia_en' => '2026-09-27',
            'termina_en' => '2026-09-30',
        ]))
        ->assertSessionHasNoErrors();

    // Un dia que ya paso en todas partes si se rechaza.
    $this->post(route('programas.store'), datosPrograma(['nombre' => 'Otro', 'inicia_en' => '2026-09-26']))
        ->assertSessionHasErrors('inicia_en');
});

test('un programa termina cuando su ultimo dia acabo en todas las zonas horarias', function () {
    $programa = Programa::factory()->create(['estado' => 'activo', 'termina_en' => '2026-09-27']);

    // 00:55 UTC del 28: en America todavia es el 27, el programa sigue abierto.
    $this->travelTo(CarbonImmutable::parse('2026-09-28 00:55:00', 'UTC'));
    expect($programa->fresh()->haTerminado())->toBeFalse()
        ->and($programa->fresh()->fuera_de_fechas)->toBeFalse();

    // 12:30 UTC del 28: el 27 ya termino incluso en UTC-12.
    $this->travelTo(CarbonImmutable::parse('2026-09-28 12:30:00', 'UTC'));
    expect($programa->fresh()->haTerminado())->toBeTrue();
});

test('un programa empieza en cuanto su primer dia comienza en algun lugar', function () {
    $programa = Programa::factory()->create(['estado' => 'activo', 'inicia_en' => '2026-09-28']);

    // 09:00 UTC del 27: en UTC+14 ya son las 23:00 del 27, todavia no empieza.
    $this->travelTo(CarbonImmutable::parse('2026-09-27 09:00:00', 'UTC'));
    expect($programa->fresh()->fuera_de_fechas)->toBeTrue();

    // 10:30 UTC del 27: en UTC+14 ya es el 28.
    $this->travelTo(CarbonImmutable::parse('2026-09-27 10:30:00', 'UTC'));
    expect($programa->fresh()->fuera_de_fechas)->toBeFalse();
});

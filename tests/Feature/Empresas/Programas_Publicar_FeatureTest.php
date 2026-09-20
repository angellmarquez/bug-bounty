<?php

use App\Enums\EstadoPrograma;
use App\Models\Empresa;
use App\Models\EventoReporte;
use App\Models\Programa;

function programaBorradorDe(Empresa $empresa): Programa
{
    return Programa::factory()->create([
        'empresa_id' => $empresa->id,
        'estado' => EstadoPrograma::Borrador,
        'es_publico' => true,
    ]);
}

test('empresa member is redirected to its new borrador programa without 403', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $this->actingAs(miembroDeEmpresa($empresa));

    $response = $this->post(route('programas.store'), [
        'nombre' => 'Programa Acme',
        'descripcion' => 'Programa de seguridad de Acme.',
        'recompensa_min' => 100,
        'recompensa_max' => 1000,
        'moneda' => 'USD',
    ]);

    $programa = Programa::where('nombre', 'Programa Acme')->firstOrFail();
    $response->assertRedirect(route('programas.show', $programa));

    $this->get(route('programas.show', $programa))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('puedeCambiarEstado', true)
            ->where('transicionesPermitidas', ['activo', 'archivado']));
});

test('empresa member can publish its borrador programa', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaBorradorDe($empresa);
    $this->actingAs(miembroDeEmpresa($empresa));

    $this->post(route('programas.cambiar-estado', $programa), ['estado' => 'activo'])
        ->assertRedirect();

    expect($programa->fresh()->estado)->toBe(EstadoPrograma::Activo);
});

test('member of another empresa cannot publish the programa', function () {
    $duena = Empresa::factory()->aprobada()->create();
    $programa = programaBorradorDe($duena);
    $this->actingAs(miembroDeEmpresa(Empresa::factory()->aprobada()->create()));

    $this->post(route('programas.cambiar-estado', $programa), ['estado' => 'activo'])
        ->assertForbidden();

    expect($programa->fresh()->estado)->toBe(EstadoPrograma::Borrador);
});

test('investigadores only see the programa once it is published', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaBorradorDe($empresa);
    $investigador = investigador();

    $this->actingAs($investigador)->get(route('programas.show', $programa))->assertForbidden();

    $programa->update(['estado' => EstadoPrograma::Activo]);

    $this->get(route('programas.show', $programa))->assertOk();
    $this->get(route('programas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('programas.data.0.id', $programa->id));
});

test('empresa dashboard summarizes reportes per programa and who approved them', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaBorradorDe($empresa);
    $programa->update(['estado' => EstadoPrograma::Activo]);
    $ajeno = programaBorradorDe(Empresa::factory()->aprobada()->create());
    $moderador = moderador(['name' => 'Moderadora Ana']);
    $this->actingAs(miembroDeEmpresa($empresa));

    $aprobado = reporteDe(investigador(), $programa, ['estado' => 'validado', 'descripcion' => 'XSS reflejado en /buscar']);
    EventoReporte::factory()->create([
        'reporte_id' => $aprobado->id,
        'actor_id' => $moderador->id,
        'tipo' => 'cambio_estado',
        'datos' => ['estado_anterior' => 'enviado', 'estado_nuevo' => 'validado'],
    ]);
    reporteDe(investigador(), $programa, ['estado' => 'enviado']);
    reporteDe(investigador(), $programa, ['estado' => 'rechazado']);
    reporteDe(investigador(), $programa, ['estado' => 'borrador']);
    reporteDe(investigador(), $ajeno, ['estado' => 'enviado']);

    $this->get(route('empresa.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('empresa.resumen', [
                'programas' => 1,
                'reportes' => 3,
                'pendientes' => 1,
                'aprobados' => 1,
                'rechazados' => 1,
            ])
            ->where('empresa.programas.0.reportes_aprobados', 1)
            ->has('empresa.reportes', 3)
            ->where('empresa.reportes', fn ($reportes) => collect($reportes)->contains(
                fn ($r) => $r['id'] === $aprobado->id
                    && $r['aprobado'] === true
                    && $r['aprobado_por'] === 'Moderadora Ana'
                    && $r['descripcion'] === 'XSS reflejado en /buscar'
            )));
});

<?php

use App\Enums\EstadoEmpresa;
use App\Enums\EstadoPrograma;
use App\Models\Empresa;
use App\Models\Programa;

function programaBorradorDe(Empresa $empresa): Programa
{
    return conObjetivo(Programa::factory()->create([
        'empresa_id' => $empresa->id,
        'estado' => EstadoPrograma::Borrador,
        'es_publico' => true,
    ]));
}

test('empresa member is redirected to its new borrador programa without 403', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $this->actingAs(miembroDeEmpresa($empresa));

    $response = $this->post(route('programas.store'), [
        'nombre' => 'Programa Acme',
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.acme.test']],
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

test('empresa dashboard summarizes reportes per programa with a compact list', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaBorradorDe($empresa);
    $programa->update(['estado' => EstadoPrograma::Activo]);
    $ajeno = programaBorradorDe(Empresa::factory()->aprobada()->create());
    $autor = investigador(['name' => 'Ana Hacker', 'reputation_score' => 42]);
    $this->actingAs(miembroDeEmpresa($empresa));

    reporteDe($autor, $programa, ['estado' => 'validado']);
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
            // La lista es compacta: sin descripción ni PoC.
            ->missing('empresa.reportes.0.descripcion')
            ->missing('empresa.reportes.0.poc')
            ->where('empresa.reportes', fn ($reportes) => collect($reportes)->contains(
                fn ($r) => $r['investigador']['name'] === 'Ana Hacker' && $r['investigador']['reputation_score'] === 42
            )));
});

test('empresa reportes page is paginated and filterable', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaBorradorDe($empresa);
    $otro = programaBorradorDe($empresa);
    $this->actingAs(miembroDeEmpresa($empresa));

    foreach (range(1, 25) as $i) {
        reporteDe(investigador(), $programa, ['estado' => 'enviado']);
    }
    $validado = reporteDe(investigador(), $otro, ['estado' => 'validado', 'titulo' => 'Bug validado unico']);
    reporteDe(investigador(), $programa, ['estado' => 'borrador']);
    reporteDe(investigador(), programaBorradorDe(Empresa::factory()->aprobada()->create()), ['estado' => 'enviado']);

    $this->get(route('empresa.reportes'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('empresa/Reportes')
            ->has('reportes.data', 20)
            ->where('reportes.total', 26)
            ->where('conteos.aprobados', 1));

    $this->get(route('empresa.reportes', ['filtro' => 'aprobados']))
        ->assertInertia(fn ($page) => $page->has('reportes.data', 1)->where('reportes.data.0.id', $validado->id));

    $this->get(route('empresa.reportes', ['programa_id' => $otro->id]))
        ->assertInertia(fn ($page) => $page->has('reportes.data', 1));

    $this->get(route('empresa.reportes', ['busqueda' => 'unico']))
        ->assertInertia(fn ($page) => $page->has('reportes.data', 1));
});

test('only members of an approved empresa can open the empresa reportes page', function () {
    $this->actingAs(investigador())->get(route('empresa.reportes'))->assertForbidden();
    $this->actingAs(miembroDeEmpresa(Empresa::factory()->create(['estado' => EstadoEmpresa::Pendiente])))
        ->get(route('empresa.reportes'))
        ->assertForbidden();
});

test('la empresa no puede crear un programa sin objetivos', function () {
    $this->actingAs(miembroDeEmpresa(Empresa::factory()->aprobada()->create()));

    $datos = [
        'nombre' => 'Programa sin alcance',
        'descripcion' => 'Descripcion',
        'recompensa_min' => 100,
        'recompensa_max' => 500,
        'moneda' => 'USD',
    ];

    $this->post(route('programas.store'), $datos)->assertSessionHasErrors('objetivos');
    $this->post(route('programas.store'), [...$datos, 'objetivos' => [['tipo' => 'web', 'valor' => '']]])
        ->assertSessionHasErrors('objetivos.0.valor');

    expect(Programa::where('nombre', 'Programa sin alcance')->exists())->toBeFalse();
});

test('un programa sin objetivos no se puede publicar', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id, 'estado' => EstadoPrograma::Borrador]);
    $this->actingAs(miembroDeEmpresa($empresa));

    $this->post(route('programas.cambiar-estado', $programa), ['estado' => 'activo'])->assertSessionHasErrors('estado');
    expect($programa->fresh()->estado)->toBe(EstadoPrograma::Borrador);

    conObjetivo($programa);
    $this->post(route('programas.cambiar-estado', $programa), ['estado' => 'activo'])->assertSessionHasNoErrors();
    expect($programa->fresh()->estado)->toBe(EstadoPrograma::Activo);
});

test('la empresa puede eliminar un programa sin informes', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaBorradorDe($empresa);
    $this->actingAs(miembroDeEmpresa($empresa));

    $this->get(route('programas.show', $programa))
        ->assertInertia(fn ($page) => $page->where('puedeEliminar', true));

    $this->delete(route('programas.destroy', $programa))->assertRedirect(route('empresa.dashboard'));

    expect(Programa::find($programa->id))->toBeNull();
});

test('un programa con informes no se puede eliminar, solo archivar', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaBorradorDe($empresa);
    reporteDe(investigador(), $programa, ['estado' => 'enviado']);
    $this->actingAs(miembroDeEmpresa($empresa));

    $this->get(route('programas.show', $programa))
        ->assertInertia(fn ($page) => $page->where('puedeEliminar', false));

    $this->delete(route('programas.destroy', $programa))->assertSessionHasErrors('programa');
    expect(Programa::find($programa->id))->not->toBeNull();

    $this->post(route('programas.cambiar-estado', $programa), ['estado' => 'archivado'])->assertSessionHasNoErrors();
    expect($programa->fresh()->estado)->toBe(EstadoPrograma::Archivado);
});

test('una empresa ajena no puede eliminar el programa', function () {
    $programa = programaBorradorDe(Empresa::factory()->aprobada()->create());
    $this->actingAs(miembroDeEmpresa(Empresa::factory()->aprobada()->create()));

    $this->delete(route('programas.destroy', $programa))->assertForbidden();
    expect(Programa::find($programa->id))->not->toBeNull();
});

test('el moderador puede ver los programas que reciben informes', function () {
    $programa = programaBorradorDe(Empresa::factory()->aprobada()->create());
    $this->actingAs(moderador());

    $this->get(route('programas.show', $programa))->assertOk();
    $this->get(route('programas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('programas.data.0.id', $programa->id));
});

test('los roles del usuario se comparten en todas las paginas para el menu lateral', function () {
    $this->actingAs(moderador())
        ->get(route('moderacion.index'))
        ->assertInertia(fn ($page) => $page->where('userRoles', ['moderador']));

    $this->actingAs(administrador())
        ->get(route('reportes.index'))
        ->assertInertia(fn ($page) => $page->where('userRoles', ['administrador']));
});

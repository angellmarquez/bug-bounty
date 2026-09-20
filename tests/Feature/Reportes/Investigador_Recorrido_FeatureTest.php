<?php

use App\Enums\EstadoPrograma;
use App\Models\Empresa;
use App\Models\EventoReporte;
use App\Models\Programa;
use App\Models\Reporte;

test('un investigador recien registrado ve el programa, reporta y sigue su informe', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create([
        'empresa_id' => $empresa->id,
        'estado' => EstadoPrograma::Activo,
        'es_publico' => true,
        'reputacion_minima' => 0,
    ]);

    $this->post(route('register.store'), [
        'name' => 'Nuevo Investigador',
        'email' => 'nuevo@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    $this->get(route('programas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('programas.data.0.id', $programa->id));

    $this->get(route('programas.show', $programa))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('puedeReportar', true));

    $this->get(route('reportes.create', ['programa' => $programa->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('programaInicial.id', $programa->id));

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'XSS en el buscador',
        'descripcion' => 'El parametro q no se escapa.',
        'poc' => ['pasos' => 'Abrir /buscar?q=<script>'],
    ])->assertRedirect();

    $reporte = Reporte::where('titulo', 'XSS en el buscador')->firstOrFail();

    $this->post(route('reportes.enviar', $reporte))->assertRedirect();
    $this->get(route('reportes.show', $reporte))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporte.estado', 'enviado')
            ->has('reporte.eventos', 2));
});

test('el investigador ve quien es la empresa y que bugs busca, sin datos de informes ajenos', function () {
    $empresa = Empresa::factory()->aprobada()->create(['nombre_comercial' => 'Acme Seguridad', 'sitio_web' => 'https://acme.test']);
    $programa = Programa::factory()->create([
        'empresa_id' => $empresa->id,
        'estado' => EstadoPrograma::Activo,
        'es_publico' => true,
        'bugs_buscados' => 'Inyeccion SQL y fallos de autenticacion',
    ]);
    reporteDe(investigador(), $programa, ['estado' => 'enviado', 'titulo' => 'Titulo de otro investigador']);
    $this->actingAs(investigador());

    $this->get(route('programas.show', $programa))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('programa.empresa.nombre', 'Acme Seguridad')
            ->where('programa.empresa.sitio_web', 'https://acme.test')
            ->where('programa.bugs_buscados', 'Inyeccion SQL y fallos de autenticacion')
            ->where('programa.reportes_count', 1)
            ->where('programa.creador', null)
            ->missing('programa.reportes'));
});

test('la empresa puede indicar que bugs busca al crear el programa', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $this->actingAs(miembroDeEmpresa($empresa));

    $this->post(route('programas.store'), [
        'nombre' => 'Programa con bugs',
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.acme.test']],
        'descripcion' => 'Descripcion',
        'bugs_buscados' => 'XSS almacenado',
        'recompensa_min' => 100,
        'recompensa_max' => 500,
        'moneda' => 'USD',
    ])->assertRedirect();

    expect(Programa::where('nombre', 'Programa con bugs')->value('bugs_buscados'))->toBe('XSS almacenado');
});

test('el panel del investigador trae el ultimo movimiento de cada informe', function () {
    $investigador = investigador();
    $programa = Programa::factory()->create(['estado' => EstadoPrograma::Activo, 'es_publico' => true]);
    $reporte = reporteDe($investigador, $programa, ['estado' => 'validado']);
    EventoReporte::factory()->create([
        'reporte_id' => $reporte->id,
        'tipo' => 'cambio_estado',
        'nota' => 'Reporte validado.',
    ]);
    $this->actingAs($investigador);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('misReportes.0.programa.id', $programa->id)
            ->where('misReportes.0.ultimo_evento.nota', 'Reporte validado.'));
});

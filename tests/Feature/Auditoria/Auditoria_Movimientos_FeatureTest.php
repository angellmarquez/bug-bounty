<?php

use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Programa;
use App\Models\Reporte;
use Illuminate\Support\Facades\Route;

test('crear y enviar un reporte queda auditado con el alias corto de entidad', function () {
    $investigador = investigador();
    $programa = Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]);
    $this->actingAs($investigador);

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'XSS reflejado',
        'descripcion' => 'Detalle',
        'poc' => ['evidencia' => 'pasos'],
        'enviar' => true,
    ])->assertRedirect();

    $reporte = Reporte::where('titulo', 'XSS reflejado')->firstOrFail();

    expect(Auditoria::where('accion', 'reportes.creado')->where('entidad_type', 'Reporte')->where('entidad_id', $reporte->id)->exists())->toBeTrue()
        ->and(Auditoria::where('accion', 'reportes.enviado')->where('entidad_id', $reporte->id)->where('usuario_id', $investigador->id)->exists())->toBeTrue();
});

test('el triaje del moderador (validar, rechazar, duplicado) queda auditado', function () {
    $programa = Programa::factory()->create();
    $moderador = moderadorDe($programa);

    $reporte = reporteDe(investigador(), $programa, ['estado' => 'en_revision', 'asignado_a' => $moderador->id]);
    $this->actingAs($moderador)->post(route('reportes.validar', $reporte))->assertRedirect();

    expect(Auditoria::where('accion', 'reportes.validado')->where('entidad_type', 'Reporte')->where('entidad_id', $reporte->id)->where('usuario_id', $moderador->id)->exists())->toBeTrue();

    $otro = reporteDe(investigador(), $programa, ['estado' => 'enviado', 'asignado_a' => $moderador->id]);
    $this->actingAs($moderador)->post(route('reportes.rechazar', $otro), ['nota' => 'no aplica'])->assertRedirect();

    expect(Auditoria::where('accion', 'reportes.rechazado')->where('entidad_id', $otro->id)->exists())->toBeTrue();
});

test('las acciones de la empresa sobre su programa quedan auditadas', function () {
    $dueno = propietarioDeEmpresa();
    $this->actingAs($dueno);

    $this->post(route('programas.store'), [
        'nombre' => 'API de Pagos',
        'descripcion' => 'Descripcion',
        'objetivos' => [['tipo' => 'api', 'valor' => 'https://api.test']],
    ])->assertRedirect();

    $programa = Programa::where('nombre', 'API de Pagos')->firstOrFail();
    expect(Auditoria::where('accion', 'programas.creado')->where('entidad_type', 'Programa')->where('entidad_id', $programa->id)->exists())->toBeTrue();

    $this->put(route('programas.update', $programa), ['nombre' => 'API de Pagos v2', 'descripcion' => 'Descripcion'])->assertRedirect();
    expect(Auditoria::where('accion', 'programas.editado')->where('entidad_id', $programa->id)->exists())->toBeTrue();

    conObjetivo($programa);
    $this->post(route('programas.cambiar-estado', $programa), ['estado' => 'activo'])->assertRedirect();
    expect(Auditoria::where('accion', 'programas.estado_cambiado')->where('entidad_id', $programa->id)->exists())->toBeTrue();
});

test('la empresa cerrando un reporte validado queda auditada', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $dueno = miembroDeEmpresa($empresa);
    $reporte = reporteDe(investigador(), $programa, ['estado' => 'validado']);

    $this->actingAs($dueno)->post(route('reportes.cerrar', $reporte))->assertRedirect();

    expect(Auditoria::where('accion', 'reportes.cerrado')->where('entidad_id', $reporte->id)->where('usuario_id', $dueno->id)->exists())->toBeTrue();
});

test('el admin filtra la auditoria por tipo de entidad y el filtro realmente separa', function () {
    $programa = Programa::factory()->create();
    $reporte = reporteDe(investigador(), $programa, ['estado' => 'validado']);
    Auditoria::registrar('reportes.cerrado', $reporte);
    Auditoria::registrar('programas.creado', $programa);

    $this->actingAs(administrador());

    $soloReportes = $this->get(route('admin.auditoria', ['entidad' => 'Reporte']))->inertiaProps()['auditoria']['data'];
    $soloProgramas = $this->get(route('admin.auditoria', ['entidad' => 'Programa']))->inertiaProps()['auditoria']['data'];

    expect(collect($soloReportes)->pluck('entidad_type')->unique()->all())->toBe(['Reporte'])
        ->and(collect($soloProgramas)->pluck('entidad_type')->unique()->all())->toBe(['Programa']);
});

test('el admin filtra la auditoria por rol del actor, incluyendo "sistema" para acciones sin usuario', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $dueno = miembroDeEmpresa($empresa);
    $moderador = moderadorDe(Programa::factory()->create());

    Auditoria::registrar('programas.creado', null, [], $dueno->id);
    Auditoria::registrar('reportes.validado', null, [], $moderador->id);
    Auditoria::registrar('pgp.clave_generada', null, [], null);

    $this->actingAs(administrador());

    $soloEmpresa = $this->get(route('admin.auditoria', ['rol' => 'empresa']))->inertiaProps()['auditoria']['data'];
    $soloModerador = $this->get(route('admin.auditoria', ['rol' => 'moderador']))->inertiaProps()['auditoria']['data'];
    $soloSistema = $this->get(route('admin.auditoria', ['rol' => 'sistema']))->inertiaProps()['auditoria']['data'];

    expect(collect($soloEmpresa)->pluck('accion')->all())->toBe(['programas.creado'])
        ->and(collect($soloModerador)->pluck('accion')->all())->toBe(['reportes.validado'])
        ->and(collect($soloSistema)->pluck('accion')->all())->toBe(['pgp.clave_generada'])
        ->and(collect($soloSistema)->pluck('usuario')->all())->toBe([null]);
});

test('la pagina de auditoria es de solo lectura: no hay rutas de escritura bajo admin/auditoria', function () {
    $rutasDeEscritura = collect(Route::getRoutes())
        ->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'admin/auditoria') && array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $ruta->methods()) !== [])
        ->count();

    expect($rutasDeEscritura)->toBe(0);
});

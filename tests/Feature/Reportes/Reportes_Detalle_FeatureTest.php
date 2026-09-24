<?php

use App\Models\Empresa;
use App\Models\EventoReporte;
use App\Models\Programa;

test('guests are redirected to the login page', function () {
    $reporte = reporteDe(investigador());
    $response = $this->get(route('reportes.show', $reporte));
    $response->assertRedirect(route('login'));
});

test('investigador can view own reporte', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('reportes/Show'));
});

test('investigador cannot view reporte de otro', function () {
    $user = investigador();
    $this->actingAs($user);

    $otro = investigador();
    $reporteAjeno = reporteDe($otro);

    $response = $this->get(route('reportes.show', $reporteAjeno));
    $response->assertForbidden();
});

test('moderador sees the reportes of its programa but never the borradores', function (string $estado, int $status) {
    $programaModerado = Programa::factory()->create();
    $this->actingAs(moderadorDe($programaModerado));

    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => $estado]);

    $this->get(route('reportes.show', $reporte))->assertStatus($status);
})->with([
    'enviado' => ['enviado', 200],
    'borrador' => ['borrador', 403],
]);

test('moderador cannot view a reporte from a programa it does not moderate', function () {
    $this->actingAs(moderadorDe(Programa::factory()->create()));

    $ajeno = reporteDe(investigador(), Programa::factory()->create(), ['estado' => 'enviado']);

    $this->get(route('reportes.show', $ajeno))->assertForbidden();
});

test('admin can view reportes de otros to review them', function () {
    $this->actingAs(administrador());

    $reporte = reporteDe(investigador(), null, ['estado' => 'enviado', 'notas_internas' => 'Nota de revision']);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $this->assertTrue($response->inertiaProps()['puedeVerNotasInternas']);
});

test('la empresa ve los informes de sus programas ya decididos por moderacion, aprobados o descartados, nunca los que se revisan', function (string $estado, bool $ve) {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));

    $reporte = reporteDe(investigador(), $programa, ['estado' => $estado]);

    $this->get(route('reportes.show', $reporte))->assertStatus($ve ? 200 : 403);
})->with([
    ['enviado', false],
    ['en_revision', false],
    ['needs_info', false],
    ['rechazado', true],
    ['duplicado', true],
    ['fuera_de_alcance', true],
    ['validado', true],
    ['en_reparacion', true],
    ['cerrado', true],
]);

test('empresa member cannot view borradores', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));

    $reporte = reporteDe(investigador(), $programa, ['estado' => 'borrador']);

    $this->get(route('reportes.show', $reporte))->assertForbidden();
});

test('member of another empresa cannot view the reporte', function () {
    $duena = Empresa::factory()->aprobada()->create();
    $otra = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $duena->id]);
    $this->actingAs(miembroDeEmpresa($otra));

    $reporte = reporteDe(investigador(), $programa, ['estado' => 'enviado']);

    $this->get(route('reportes.show', $reporte))->assertForbidden();
});

test('investigador does not see notas internas', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user, null, [
        'notas_internas' => 'Nota privada',
    ]);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();
    $this->assertFalse($props['puedeVerNotasInternas']);
    $this->assertArrayNotHasKey('notas_internas', $props['reporte']);
});

test('reporte show includes timeline eventos', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user);
    EventoReporte::factory()->create([
        'reporte_id' => $reporte->id,
        'tipo' => 'creado',
        'nota' => 'Reporte creado por el investigador',
    ]);
    EventoReporte::factory()->create([
        'reporte_id' => $reporte->id,
        'tipo' => 'enviado',
        'nota' => 'Enviado para revision',
    ]);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertCount(2, $props['reporte']['eventos']);
    // El timeline muestra primero lo más reciente.
    $this->assertEquals('enviado', $props['reporte']['eventos'][0]['tipo']);
    $this->assertEquals('creado', $props['reporte']['eventos'][1]['tipo']);
});

test('reporte show includes actor in eventos', function () {
    $user = investigador();
    $this->actingAs($user);

    $reporte = reporteDe($user);
    EventoReporte::factory()->create([
        'reporte_id' => $reporte->id,
        'actor_id' => $user->id,
        'tipo' => 'creado',
    ]);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertNotNull($props['reporte']['eventos'][0]['actor']);
    $this->assertEquals($user->id, $props['reporte']['eventos'][0]['actor']['id']);
});

test('reporte show includes relaciones', function () {
    $user = investigador();
    $this->actingAs($user);

    $programa = Programa::factory()->create(['nombre' => 'Programa Test']);
    $reporte = reporteDe($user, $programa);

    $response = $this->get(route('reportes.show', $reporte));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertNotNull($props['reporte']['programa']);
    $this->assertEquals('Programa Test', $props['reporte']['programa']['nombre']);
    $this->assertNotNull($props['reporte']['investigador']);
    $this->assertEquals($user->id, $props['reporte']['investigador']['id']);
});

test('reporte duplicado includes duplicadoDe', function () {
    $user = investigador();
    $this->actingAs($user);

    $original = reporteDe($user);
    $duplicado = reporteDe($user, null, [
        'es_duplicado_de' => $original->id,
    ]);

    $response = $this->get(route('reportes.show', $duplicado));
    $response->assertOk();
    $props = $response->inertiaProps();

    $this->assertNotNull($props['reporte']['duplicadoDe']);
    $this->assertEquals($original->id, $props['reporte']['duplicadoDe']['id']);
    $this->assertEquals($original->numero_reporte, $props['reporte']['duplicadoDe']['numero_reporte']);
});

test('reporte inexistente returns 404', function () {
    $user = investigador();
    $this->actingAs($user);

    $response = $this->get(route('reportes.show', 99999));
    $response->assertNotFound();
});

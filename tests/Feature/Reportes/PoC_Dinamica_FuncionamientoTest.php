<?php

use App\Models\Empresa;
use App\Models\Programa;
use App\Models\Reporte;
use App\Services\Pgp\PgpService;

/*
|--------------------------------------------------------------------------
| Prueba de funcionamiento end-to-end del formulario dinámico de PoC
|--------------------------------------------------------------------------
|
| Simula el recorrido real: la empresa define su propio cuestionario de
| evidencia por programa, el investigador lo ve y lo completa, y el sistema
| bloquea el envío si falta algo obligatorio (todo programa exige PoC).
|
*/

test('la empresa define su propio cuestionario y el investigador lo ve reflejado en el formulario', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $dueno = miembroDeEmpresa($empresa);

    // La empresa arma su propio schema de PoC (esto es lo que hace PocSchemaEditor.svelte).
    $this->actingAs($dueno)->post(route('programas.store'), [
        'nombre' => 'API Bancaria',
        'descripcion' => 'Programa para la API de pagos.',
        'objetivos' => [['tipo' => 'api', 'valor' => 'https://api.banco.test']],
        'poc_schema' => [
            ['name' => 'endpoint', 'label' => 'Endpoint afectado', 'type' => 'url', 'required' => true],
            ['name' => 'metodo', 'label' => 'Método HTTP', 'type' => 'select', 'required' => true,
                'options' => [['value' => 'GET', 'label' => 'GET'], ['value' => 'POST', 'label' => 'POST']]],
            ['name' => 'payload', 'label' => 'Payload usado', 'type' => 'code', 'required' => true],
        ],
    ])->assertRedirect();

    $programa = Programa::where('nombre', 'API Bancaria')->firstOrFail();
    $programa->update(['estado' => 'activo', 'es_publico' => true]);

    // El investigador abre "crear reporte" para ESE programa: el formulario debe
    // traer exactamente los 3 campos que la empresa definió, ni uno más ni uno menos.
    $investigador = investigador();
    $props = $this->actingAs($investigador)
        ->get(route('reportes.create', ['programa' => $programa->id]))
        ->assertOk()
        ->inertiaProps();

    $campos = collect($props['programaInicial']['poc_schema'])->pluck('name')->all();
    expect($campos)->toBe(['endpoint', 'metodo', 'payload']);
});

test('bloquea el envio si falta un campo obligatorio del schema del programa, pero deja guardar el progreso', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaDeEmpresa(miembroDeEmpresa($empresa), [
        'estado' => 'activo',
        'es_publico' => true,
        'poc_schema' => [
            ['name' => 'endpoint', 'label' => 'Endpoint afectado', 'type' => 'url', 'required' => true],
            ['name' => 'payload', 'label' => 'Payload usado', 'type' => 'textarea', 'required' => true],
        ],
    ]);
    $investigador = investigador();
    $this->actingAs($investigador);

    // Guarda un borrador con solo la mitad del cuestionario completo: debe permitirlo.
    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'IDOR en pagos',
        'descripcion' => 'El endpoint no valida el dueño del recurso.',
        'poc' => ['endpoint' => 'https://api.banco.test/pagos/123'],
    ])->assertSessionHasNoErrors();

    $reporte = Reporte::where('titulo', 'IDOR en pagos')->firstOrFail();
    expect($reporte->estado->value)->toBe('borrador');

    // Intenta enviarlo sin completar "payload" (obligatorio): debe bloquearse.
    $this->post(route('reportes.enviar', $reporte))
        ->assertSessionHasErrors('poc');
    expect($reporte->fresh()->estado->value)->toBe('borrador');

    // Completa el campo que faltaba y ahora sí se puede enviar.
    $this->put(route('reportes.update', $reporte), [
        'poc' => ['endpoint' => 'https://api.banco.test/pagos/123', 'payload' => 'GET /pagos/123 con token de otro usuario'],
    ])->assertSessionHasNoErrors();

    $this->post(route('reportes.enviar', $reporte))->assertRedirect();
    expect($reporte->fresh()->estado->value)->toBe('enviado');

    // La PoC quedó cifrada en la base y se puede descifrar con los valores reales.
    $descifrado = app(PgpService::class)->descifrarReporte($reporte->fresh()->descripcion, $reporte->fresh()->poc)['poc'];
    expect($descifrado)->toBe([
        'endpoint' => 'https://api.banco.test/pagos/123',
        'payload' => 'GET /pagos/123 con token de otro usuario',
    ]);
});

test('dos programas distintos muestran formularios distintos: eso es lo dinamico', function () {
    $empresaA = Empresa::factory()->aprobada()->create();
    $programaA = programaDeEmpresa(miembroDeEmpresa($empresaA), [
        'estado' => 'activo',
        'es_publico' => true,
        'poc_schema' => [['name' => 'apk_version', 'label' => 'Versión de APK', 'type' => 'text', 'required' => true]],
    ]);

    $empresaB = Empresa::factory()->aprobada()->create();
    $programaB = programaDeEmpresa(miembroDeEmpresa($empresaB), [
        'estado' => 'activo',
        'es_publico' => true,
        'poc_schema' => [['name' => 'url_afectada', 'label' => 'URL afectada', 'type' => 'url', 'required' => true]],
    ]);

    $investigador = investigador();
    $this->actingAs($investigador);

    $propsA = $this->get(route('reportes.create', ['programa' => $programaA->id]))->inertiaProps();
    $propsB = $this->get(route('reportes.create', ['programa' => $programaB->id]))->inertiaProps();

    expect(collect($propsA['programaInicial']['poc_schema'])->pluck('name')->all())->toBe(['apk_version'])
        ->and(collect($propsB['programaInicial']['poc_schema'])->pluck('name')->all())->toBe(['url_afectada']);
});

test('un programa sin schema propio igual exige evidencia: no se puede omitir el paso de PoC', function () {
    $programa = Programa::factory()->create(['estado' => 'activo', 'es_publico' => true, 'poc_schema' => null]);
    $investigador = investigador();
    $this->actingAs($investigador);

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Sin evidencia',
        'descripcion' => 'Descripcion',
        'poc' => null,
        'enviar' => true,
    ])->assertSessionHasErrors('poc');

    $this->assertDatabaseMissing('reportes', ['titulo' => 'Sin evidencia']);
});

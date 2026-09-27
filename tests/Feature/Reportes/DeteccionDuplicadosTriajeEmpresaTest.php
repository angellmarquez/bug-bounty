<?php

use App\Models\Empresa;
use App\Models\Programa;
use App\Models\Reporte;
use Inertia\Testing\AssertableInertia as Assert;

test('moderador ve candidatos a duplicado enriquecidos y ordenados con sugerencias de coincidencia', function () {
    $programa = Programa::factory()->create();
    $moderador = moderadorDe($programa);
    $this->actingAs($moderador);

    $originalSql = reporteDe(investigador(), $programa, [
        'titulo' => 'SQL Injection en endpoint /api/v2/buscar',
        'categoria' => 'Inyección SQL',
        'estado' => 'enviado',
        'created_at' => now()->subHours(5),
        'enviado_en' => now()->subHours(5),
    ]);

    $otroReporte = reporteDe(investigador(), $programa, [
        'titulo' => 'Falta de cabeceras CSP',
        'categoria' => 'Configuración de Seguridad',
        'estado' => 'enviado',
        'created_at' => now()->subHours(4),
        'enviado_en' => now()->subHours(4),
    ]);

    $nuevoReporte = reporteDe(investigador(), $programa, [
        'titulo' => 'Inyección SQL en búsqueda pública',
        'categoria' => 'Inyección SQL',
        'estado' => 'enviado',
        'asignado_a' => $moderador->id,
        'created_at' => now()->subHour(),
        'enviado_en' => now()->subHour(),
    ]);

    $response = $this->get(route('reportes.show', $nuevoReporte));
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('reportes/Show')
        ->has('candidatosDuplicado', 2)
        ->where('candidatosDuplicado.0.id', $originalSql->id)
        ->where('candidatosDuplicado.0.es_sugerido', true)
        ->where('candidatosDuplicado.0.coincide_categoria', true)
        ->has('posiblesDuplicados', 1)
        ->where('posiblesDuplicados.0.id', $originalSql->id)
        ->where('accionesDisponibles.marcar_duplicado', true)
    );
});

test('empresa dueña del programa puede marcar como duplicado un reporte validado', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $usuarioEmpresa = miembroDeEmpresa($empresa);
    $this->actingAs($usuarioEmpresa);

    $original = reporteDe(investigador(), $programa, [
        'titulo' => 'XSS reflejado en buscador',
        'estado' => 'validado',
        'created_at' => now()->subDays(2),
        'enviado_en' => now()->subDays(2),
    ]);

    $duplicado = reporteDe(investigador(), $programa, [
        'titulo' => 'Cross-Site Scripting en parámetro q',
        'estado' => 'validado',
        'created_at' => now()->subDay(),
        'enviado_en' => now()->subDay(),
    ]);

    // La empresa ve el reporte y tiene disponible la acción marcar_duplicado
    $showResponse = $this->get(route('reportes.show', $duplicado));
    $showResponse->assertOk();
    $showResponse->assertInertia(fn (Assert $page) => $page
        ->component('reportes/Show')
        ->where('accionesDisponibles.marcar_duplicado', true)
    );

    // La empresa ejecuta la acción de marcar como duplicado
    $response = $this->post(route('reportes.marcar-duplicado', $duplicado), [
        'reporte_duplicado_id' => $original->id,
        'nota' => 'Confirmado como duplicado por el equipo de seguridad de la empresa.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reportes', [
        'id' => $duplicado->id,
        'estado' => 'duplicado',
        'es_duplicado_de' => $original->id,
    ]);

    $this->assertDatabaseHas('eventos_reporte', [
        'reporte_id' => $duplicado->id,
        'tipo' => 'marcado_duplicado',
        'actor_id' => $usuarioEmpresa->id,
    ]);
});

test('empresa NO puede marcar como duplicado un reporte que no pertenece a su programa', function () {
    $empresaA = Empresa::factory()->aprobada()->create();
    $empresaB = Empresa::factory()->aprobada()->create();

    $programaA = Programa::factory()->create(['empresa_id' => $empresaA->id]);
    $programaB = Programa::factory()->create(['empresa_id' => $empresaB->id]);

    $this->actingAs(miembroDeEmpresa($empresaA));

    $original = reporteDe(investigador(), $programaB, [
        'estado' => 'validado',
        'created_at' => now()->subDays(2),
        'enviado_en' => now()->subDays(2),
    ]);

    $duplicado = reporteDe(investigador(), $programaB, [
        'estado' => 'validado',
        'created_at' => now()->subDay(),
        'enviado_en' => now()->subDay(),
    ]);

    $response = $this->post(route('reportes.marcar-duplicado', $duplicado), [
        'reporte_duplicado_id' => $original->id,
        'nota' => 'Intento ilegítimo de marcar reporte ajeno.',
    ]);

    $response->assertForbidden();
});

test('empresa NO puede marcar como duplicado un reporte si aún está en revisión por moderación', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));

    $original = reporteDe(investigador(), $programa, [
        'estado' => 'validado',
        'created_at' => now()->subDays(2),
        'enviado_en' => now()->subDays(2),
    ]);

    $reporteEnRevision = reporteDe(investigador(), $programa, [
        'estado' => 'en_revision',
        'created_at' => now()->subDay(),
        'enviado_en' => now()->subDay(),
    ]);

    $response = $this->post(route('reportes.marcar-duplicado', $reporteEnRevision), [
        'reporte_duplicado_id' => $original->id,
    ]);

    $response->assertForbidden();
});

test('no se puede marcar duplicado de un reporte de otro programa diferente', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa1 = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $programa2 = Programa::factory()->create(['empresa_id' => $empresa->id]);

    $this->actingAs(miembroDeEmpresa($empresa));

    $originalEnOtroPrograma = reporteDe(investigador(), $programa2, [
        'estado' => 'validado',
        'created_at' => now()->subDays(2),
        'enviado_en' => now()->subDays(2),
    ]);

    $reporte = reporteDe(investigador(), $programa1, [
        'estado' => 'validado',
        'created_at' => now()->subDay(),
        'enviado_en' => now()->subDay(),
    ]);

    $response = $this->post(route('reportes.marcar-duplicado', $reporte), [
        'reporte_duplicado_id' => $originalEnOtroPrograma->id,
        'nota' => 'Intento de vincular duplicado cruzado.',
    ]);

    $response->assertStatus(422);
});

test('no se puede marcar duplicado de un reporte que llegó después temporalmente', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $this->actingAs(miembroDeEmpresa($empresa));

    $reporteAntiguo = reporteDe(investigador(), $programa, [
        'estado' => 'validado',
        'created_at' => now()->subDays(2),
        'enviado_en' => now()->subDays(2),
    ]);

    $reporteNuevo = reporteDe(investigador(), $programa, [
        'estado' => 'validado',
        'created_at' => now()->subDay(),
        'enviado_en' => now()->subDay(),
    ]);

    // Intentar marcar el reporte antiguo como duplicado del nuevo (violación de orden temporal)
    $response = $this->post(route('reportes.marcar-duplicado', $reporteAntiguo), [
        'reporte_duplicado_id' => $reporteNuevo->id,
        'nota' => 'Intento de marcar como duplicado de un reporte posterior.',
    ]);

    $response->assertStatus(422);
});

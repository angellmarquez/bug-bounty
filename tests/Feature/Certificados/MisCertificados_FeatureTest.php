<?php

use App\Enums\GravedadSancion;
use App\Models\Programa;
use App\Services\Certificados\CertificadoService;
use App\Services\Reputacion\ReputationService;

test('el investigador ve en un solo lugar los certificados de sus informes cerrados', function () {
    $investigador = investigador();
    $programa = Programa::factory()->create(['estado' => 'activo']);
    $antiguo = reporteDe($investigador, $programa, ['estado' => 'cerrado', 'cerrado_en' => now()->subMonth(), 'titulo' => 'IDOR en facturas']);
    $reciente = reporteDe($investigador, $programa, ['estado' => 'cerrado', 'cerrado_en' => now(), 'titulo' => 'SQLi en buscador']);
    $certificado = app(CertificadoService::class)->obtenerOCrear($reciente, $investigador);

    // No entran: informes sin cerrar ni los de otros investigadores.
    reporteDe($investigador, $programa, ['estado' => 'validado']);
    reporteDe(investigador(), $programa, ['estado' => 'cerrado', 'cerrado_en' => now()]);

    $this->actingAs($investigador)
        ->get(route('certificados.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('certificados/Index')
            ->has('certificados', 2)
            ->where('certificados.0.reporte_id', $reciente->id)
            ->where('certificados.0.codigo', $certificado->codigo)
            ->where('certificados.0.url_verificacion', route('certificados.verificar', ['codigo' => $certificado->codigo]))
            ->where('certificados.1.reporte_id', $antiguo->id)
            ->where('certificados.1.titulo', 'IDOR en facturas'));
});

test('un informe cerrado que aun no tenia certificado lo recibe al abrir la lista, sin avisarse a si mismo', function () {
    $investigador = investigador();
    $reporte = reporteDe($investigador, Programa::factory()->create(), ['estado' => 'cerrado', 'cerrado_en' => now()]);
    expect($reporte->certificado)->toBeNull();

    $this->actingAs($investigador)
        ->get(route('certificados.index'))
        ->assertInertia(fn ($page) => $page->where('certificados.0.codigo', fn ($codigo) => str_starts_with($codigo, 'BB-CERT-')));

    expect($reporte->fresh()->certificado)->not->toBeNull()
        ->and($investigador->notifications()->count())->toBe(0);
});

test('un investigador suspendido sigue teniendo acceso a sus certificados', function () {
    $investigador = investigador();
    $reporte = reporteDe($investigador, Programa::factory()->create(), ['estado' => 'cerrado', 'cerrado_en' => now()]);
    app(ReputationService::class)->aplicarSancion($investigador, 'violacion_normas', GravedadSancion::Grave);
    expect($investigador->fresh()->suspensionActiva())->not->toBeNull();

    $this->actingAs($investigador->fresh())
        ->get(route('certificados.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('certificados', 1));

    $this->actingAs($investigador->fresh())
        ->get(route('certificados.show', $reporte))
        ->assertOk();
});

test('una cuenta desactivada no ve certificados', function () {
    $investigador = investigador(['is_active' => false]);
    reporteDe($investigador, Programa::factory()->create(), ['estado' => 'cerrado', 'cerrado_en' => now()]);

    $this->actingAs($investigador)
        ->get(route('certificados.index'))
        ->assertInertia(fn ($page) => $page->has('certificados', 0));
});

test('sin informes cerrados la lista esta vacia', function () {
    $this->actingAs(investigador())
        ->get(route('certificados.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('certificados/Index')->has('certificados', 0));
});

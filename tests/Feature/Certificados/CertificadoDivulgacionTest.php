<?php

use App\Models\Empresa;
use App\Models\Programa;
use App\Services\Certificados\CertificadoService;

test('un reporte aprobado genera un certificado sellado con SHA-256 y firma PGP', function () {
    $investigador = investigador();
    $empresa = Empresa::factory()->create();
    $programa = Programa::factory()->create(['estado' => 'activo', 'empresa_id' => $empresa->id]);
    $reporte = reporteDe($investigador, $programa, [
        'estado' => 'validado',
        'puntuacion_cvss' => 7.5,
        'severidad' => 'alta',
    ]);

    $service = app(CertificadoService::class);
    $certificado = $service->obtenerOCrear($reporte, $investigador);

    expect($certificado->codigo)->toStartWith('BB-CERT-')
        ->and($certificado->huella)->toHaveLength(64)
        ->and($certificado->firma_pgp)->not->toBeEmpty();

    $verificacion = $service->verificar($certificado);
    expect($verificacion['valido'])->toBeTrue()
        ->and($verificacion['hash_valido'])->toBeTrue()
        ->and($verificacion['firma_valida'])->toBeTrue();
});

test('si alguien altera los datos del certificado la verificacion criptografica lo detecta', function () {
    $investigador = investigador();
    $empresa = Empresa::factory()->create();
    $programa = Programa::factory()->create(['estado' => 'activo', 'empresa_id' => $empresa->id]);
    $reporte = reporteDe($investigador, $programa, ['estado' => 'cerrado', 'puntuacion_cvss' => 9.8]);

    $service = app(CertificadoService::class);
    $certificado = $service->obtenerOCrear($reporte, $investigador);

    // Alteramos los datos en la base (ej. subir puntuación de forma fraudulenta)
    $datosAlterados = $certificado->datos;
    $datosAlterados['cvss_score'] = 10.0;
    $certificado->datos = $datosAlterados;
    $certificado->save();

    $verificacion = $service->verificar($certificado->fresh());
    expect($verificacion['valido'])->toBeFalse()
        ->and($verificacion['hash_valido'])->toBeFalse();
});

test('un reporte no aprobado no permite emitir certificado', function () {
    $investigador = investigador();
    $programa = Programa::factory()->create(['estado' => 'activo']);
    $reporte = reporteDe($investigador, $programa, ['estado' => 'enviado']);

    $this->actingAs($investigador)
        ->get(route('certificados.show', $reporte))
        ->assertNotFound();
});

test('el investigador y la empresa dueña pueden ver el certificado pero usuarios ajenos reciben 403', function () {
    $investigador = investigador();
    $dueno = propietarioDeEmpresa();
    $empresa = $dueno->empresas()->first();
    $programa = Programa::factory()->create(['estado' => 'activo', 'empresa_id' => $empresa->id]);
    $reporte = reporteDe($investigador, $programa, ['estado' => 'validado']);

    // Autor puede verlo
    $this->actingAs($investigador)
        ->get(route('certificados.show', $reporte))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('reportes/Certificado'));

    // Empresa dueña puede verlo
    $this->actingAs($dueno)
        ->get(route('certificados.show', $reporte))
        ->assertOk();

    // Administrador puede verlo
    $this->actingAs(administrador())
        ->get(route('certificados.show', $reporte))
        ->assertOk();

    // Otro investigador ajeno recibe 403
    $ajeno = investigador();
    $this->actingAs($ajeno)
        ->get(route('certificados.show', $reporte))
        ->assertForbidden();
});

test('la ruta publica de verificacion no requiere login y valida el certificado', function () {
    $investigador = investigador();
    $programa = Programa::factory()->create(['estado' => 'activo']);
    $reporte = reporteDe($investigador, $programa, ['estado' => 'validado']);

    $service = app(CertificadoService::class);
    $certificado = $service->obtenerOCrear($reporte, $investigador);

    // Consulta pública sin sesión autenticada
    $this->get(route('certificados.verificar', ['codigo' => $certificado->codigo]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('VerificarCertificado')
            ->where('existe', true)
            ->where('verificacion.valido', true)
            ->where('certificado.codigo', $certificado->codigo));

    // Código inventado
    $this->get(route('certificados.verificar', ['codigo' => 'BB-CERT-INEXISTENTE']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('VerificarCertificado')
            ->where('existe', false));
});

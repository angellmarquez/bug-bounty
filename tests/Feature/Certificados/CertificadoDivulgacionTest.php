<?php

use App\Models\CertificadoDivulgacion;
use App\Models\ClavePgpPlataforma;
use App\Models\Empresa;
use App\Models\Programa;
use App\Services\Certificados\CertificadoService;
use App\Services\Pgp\Exceptions\PgpException;
use App\Services\Pgp\PgpService;

test('un reporte cerrado genera un certificado sellado con SHA-256 y firma PGP', function () {
    $investigador = investigador();
    $empresa = Empresa::factory()->create();
    $programa = Programa::factory()->create(['estado' => 'activo', 'empresa_id' => $empresa->id]);
    $reporte = reporteDe($investigador, $programa, [
        'estado' => 'cerrado',
        'cerrado_en' => now(),
        'puntuacion_cvss' => 7.5,
        'severidad' => 'alta',
    ]);

    $service = app(CertificadoService::class);
    $certificado = $service->obtenerOCrear($reporte, $investigador);

    expect($certificado->codigo)->toStartWith('BB-CERT-')
        ->and($certificado->huella)->toHaveLength(64)
        ->and($certificado->firma_pgp)->not->toBeEmpty()
        ->and($certificado->datos['version'])->toBe(CertificadoService::VERSION_ACTUAL);

    $verificacion = $service->verificar($certificado);
    expect($verificacion['valido'])->toBeTrue()
        ->and($verificacion['hash_valido'])->toBeTrue()
        ->and($verificacion['firma_valida'])->toBeTrue()
        ->and($verificacion['vigente'])->toBeTrue();
});

test('si alguien altera cualquier dato mostrado en el certificado la verificacion lo detecta', function (string $campo, mixed $valor) {
    $investigador = investigador();
    $reporte = reporteDe($investigador, Programa::factory()->create(), ['estado' => 'cerrado', 'cerrado_en' => now(), 'puntuacion_cvss' => 9.8]);

    $service = app(CertificadoService::class);
    $certificado = $service->obtenerOCrear($reporte, $investigador);

    $datos = $certificado->datos;
    $datos[$campo] = $valor;
    $certificado->datos = $datos;
    $certificado->save();

    $verificacion = $service->verificar($certificado->fresh());
    expect($verificacion['valido'])->toBeFalse()
        ->and($verificacion['hash_valido'])->toBeFalse();
})->with([
    'puntuacion CVSS' => ['cvss_score', 10.0],
    'titulo' => ['titulo', 'RCE sin autenticación en todo el sistema'],
    'investigador' => ['investigador_alias', 'Otra persona'],
    'categoria' => ['categoria', 'Ejecución remota de código'],
    'rebajar a la version 1' => ['version', 1],
]);

test('un certificado v1 emitido antes del cambio de formato se sigue verificando', function () {
    $investigador = investigador();
    $reporte = reporteDe($investigador, Programa::factory()->create(), ['estado' => 'cerrado', 'cerrado_en' => now()]);

    // Formato v1: sin `version` y con un payload que no incluía título, categoría ni autor.
    $codigo = 'BB-CERT-LEGADO0001';
    $datos = [
        'numero_reporte' => $reporte->numero_reporte,
        'titulo' => 'Título con acentos: inyección',
        'severidad' => 'alta',
        'cvss_score' => 7.5,
        'cvss_vector' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N',
        'programa_nombre' => 'Programa Ñandú',
        'empresa_nombre' => 'Empresa',
        'investigador_alias' => $investigador->name,
        'investigador_id' => $investigador->id,
        'fecha_resolucion' => now()->toIso8601String(),
    ];
    $huella = hash('sha256', json_encode([
        'codigo' => $codigo,
        'reporte_id' => $reporte->id,
        'numero_reporte' => $datos['numero_reporte'],
        'severidad' => 'alta',
        'cvss_score' => 7.5,
        'cvss_vector' => $datos['cvss_vector'],
        'programa' => 'Programa Ñandú',
        'empresa' => 'Empresa',
        'investigador_id' => $investigador->id,
        'fecha_resolucion' => $datos['fecha_resolucion'],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

    $pgp = app(PgpService::class);
    $certificado = CertificadoDivulgacion::query()->create([
        'reporte_id' => $reporte->id,
        'codigo' => $codigo,
        'huella' => $huella,
        'firma_pgp' => $pgp->sign($huella),
        'clave_huella' => $pgp->asegurarClave()->huella,
        'datos' => $datos,
    ]);

    expect(app(CertificadoService::class)->verificar($certificado)['valido'])->toBeTrue();
});

test('solo un informe cerrado admite certificado: validado o en reparacion todavia no', function (string $estado) {
    $investigador = investigador();
    $reporte = reporteDe($investigador, Programa::factory()->create(['estado' => 'activo']), ['estado' => $estado]);

    $this->actingAs($investigador)
        ->get(route('certificados.show', $reporte))
        ->assertNotFound();

    expect(fn () => app(CertificadoService::class)->obtenerOCrear($reporte))->toThrow(InvalidArgumentException::class);
})->with(['enviado', 'validado', 'en_reparacion']);

test('a un ajeno se le responde 403 aunque el informe no este cerrado, sin revelar su estado', function () {
    $reporte = reporteDe(investigador(), Programa::factory()->create(['estado' => 'activo']), ['estado' => 'validado']);

    $this->actingAs(investigador())
        ->get(route('certificados.show', $reporte))
        ->assertForbidden();
});

test('el autor, la empresa duena, el moderador del programa y el admin ven el certificado; los ajenos reciben 403', function () {
    $investigador = investigador();
    $dueno = propietarioDeEmpresa();
    $programa = programaDeEmpresa($dueno, ['estado' => 'activo']);
    $reporte = reporteDe($investigador, $programa, ['estado' => 'cerrado', 'cerrado_en' => now()]);
    $moderadorDelPrograma = moderador();
    $moderadorDelPrograma->programasModerados()->attach($programa->id);

    foreach ([$investigador, $dueno, $moderadorDelPrograma, administrador()] as $usuario) {
        $this->actingAs($usuario)
            ->get(route('certificados.show', $reporte))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('reportes/Certificado'));
    }

    foreach ([investigador(), moderador(), propietarioDeEmpresa()] as $ajeno) {
        $this->actingAs($ajeno)
            ->get(route('certificados.show', $reporte))
            ->assertForbidden();
    }
});

test('al cerrar el informe se emite el certificado y se avisa al investigador', function () {
    $investigador = investigador();
    $dueno = propietarioDeEmpresa();
    $programa = programaDeEmpresa($dueno, ['estado' => 'activo']);
    $reporte = reporteDe($investigador, $programa, ['estado' => 'en_reparacion']);

    $this->actingAs($dueno)->post(route('reportes.cerrar', $reporte))->assertRedirect();

    $certificado = $reporte->fresh()->certificado;
    expect($certificado)->not->toBeNull()
        ->and($certificado->emitido_por_id)->toBe($dueno->id)
        ->and($certificado->datos['fecha_resolucion'])->toBe($reporte->fresh()->cerrado_en->toIso8601String());

    $avisos = $investigador->notifications()->get()->pluck('data');
    expect($avisos->pluck('titulo'))->toContain('Tu certificado de divulgación está listo')
        ->and($avisos->firstWhere('titulo', 'Tu certificado de divulgación está listo')['url'])->toBe("/reportes/{$reporte->id}/certificado");
});

test('el detalle del informe solo ofrece el certificado cuando esta cerrado', function () {
    $investigador = investigador();
    $programa = Programa::factory()->create(['estado' => 'activo']);
    $validado = reporteDe($investigador, $programa, ['estado' => 'validado']);
    $cerrado = reporteDe($investigador, $programa, ['estado' => 'cerrado', 'cerrado_en' => now()]);

    $this->actingAs($investigador)->get(route('reportes.show', $validado))
        ->assertInertia(fn ($page) => $page->where('puedeVerCertificado', false));

    $this->actingAs($investigador)->get(route('reportes.show', $cerrado))
        ->assertInertia(fn ($page) => $page->where('puedeVerCertificado', true));
});

test('la ruta publica de verificacion no requiere login y valida el certificado', function () {
    $investigador = investigador();
    $reporte = reporteDe($investigador, Programa::factory()->create(['estado' => 'activo']), ['estado' => 'cerrado', 'cerrado_en' => now()]);
    $certificado = app(CertificadoService::class)->obtenerOCrear($reporte, $investigador);

    $this->get(route('certificados.verificar', ['codigo' => $certificado->codigo]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('VerificarCertificado')
            ->where('existe', true)
            ->where('verificacion.valido', true)
            ->where('verificacion.vigente', true)
            ->where('certificado.codigo', $certificado->codigo)
            ->where('descargas.firma', route('certificados.firma', ['codigo' => $certificado->codigo]))
            ->where('descargas.clave', route('certificados.clave', ['codigo' => $certificado->codigo])));

    $this->get(route('certificados.verificar', ['codigo' => 'BB-CERT-INEXISTENTE']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('VerificarCertificado')->where('existe', false));
});

test('el certificado incluye un QR que lleva a su pagina publica de verificacion', function () {
    $investigador = investigador();
    $reporte = reporteDe($investigador, Programa::factory()->create(), ['estado' => 'cerrado', 'cerrado_en' => now()]);

    $this->actingAs($investigador)->get(route('certificados.show', $reporte))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Certificado')
            ->where('qrVerificacion', fn (string $svg) => str_starts_with($svg, '<svg') && ! str_contains($svg, '<?xml')));
});

test('si la firma no se puede comprobar la pagina de verificacion lo muestra en vez de fallar', function () {
    $investigador = investigador();
    $reporte = reporteDe($investigador, Programa::factory()->create(), ['estado' => 'cerrado', 'cerrado_en' => now()]);
    $certificado = app(CertificadoService::class)->obtenerOCrear($reporte, $investigador);

    // Como GnuPG ante una firma de una clave que su llavero no conoce.
    $this->partialMock(PgpService::class, fn ($mock) => $mock->shouldReceive('verify')->andThrow(new PgpException('No hay clave pública')));

    $this->get(route('certificados.verificar', ['codigo' => $certificado->codigo]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('existe', true)
            ->where('verificacion.firma_valida', false)
            ->where('verificacion.valido', false));
});

test('si el informe se retira, el certificado sigue siendo autentico pero deja de estar vigente', function () {
    $investigador = investigador();
    $reporte = reporteDe($investigador, Programa::factory()->create(), ['estado' => 'cerrado', 'cerrado_en' => now()]);
    $certificado = app(CertificadoService::class)->obtenerOCrear($reporte, $investigador);

    $reporte->delete();

    $this->get(route('certificados.verificar', ['codigo' => $certificado->codigo]))
        ->assertInertia(fn ($page) => $page
            ->where('verificacion.valido', true)
            ->where('verificacion.vigente', false));
});

test('se descargan la firma y la clave publica, nunca la privada', function () {
    $investigador = investigador();
    $reporte = reporteDe($investigador, Programa::factory()->create(), ['estado' => 'cerrado', 'cerrado_en' => now()]);
    $certificado = app(CertificadoService::class)->obtenerOCrear($reporte, $investigador);
    $clave = ClavePgpPlataforma::query()->where('huella', $certificado->clave_huella)->firstOrFail();

    $firma = $this->get(route('certificados.firma', ['codigo' => $certificado->codigo]))->assertOk();
    expect($firma->getContent())->toBe($certificado->firma_pgp)
        ->and($firma->headers->get('Content-Disposition'))->toContain("{$certificado->codigo}.sig.asc");

    $publica = $this->get(route('certificados.clave', ['codigo' => $certificado->codigo]))->assertOk();
    expect($publica->getContent())->toBe($clave->clave_publica)
        ->and($publica->getContent())->not->toContain($clave->clave_privada)
        ->and($publica->getContent())->not->toContain('PRIVATE KEY');

    $this->get(route('certificados.firma', ['codigo' => 'BB-CERT-INEXISTENTE']))->assertNotFound();
});

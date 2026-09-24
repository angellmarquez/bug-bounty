<?php

use App\Enums\EstadoPrograma;
use App\Models\Empresa;
use App\Models\ObjetivoPrograma;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;

function programaConEmpresa(array $atributos = []): Programa
{
    return Programa::factory()->create([
        'empresa_id' => Empresa::factory()->aprobada()->create(['nombre_comercial' => 'Acme'])->id,
        'estado' => EstadoPrograma::Activo,
        ...$atributos,
    ]);
}

test('los moderadores ven la cola de moderacion por programa', function () {
    $programa = programaConEmpresa(['nombre' => 'Programa Acme']);
    $this->actingAs(moderadorDe($programa));
    reporteDe(investigador(), $programa, ['estado' => 'enviado']);
    reporteDe(investigador(), $programa, ['estado' => 'en_revision']);
    reporteDe(investigador(), $programa, ['estado' => 'validado']);
    reporteDe(investigador(), $programa, ['estado' => 'rechazado']);
    reporteDe(investigador(), $programa, ['estado' => 'borrador']);

    $this->get(route('moderacion.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderacion/Index')
            ->where('programas.data.0.nombre', 'Programa Acme')
            ->where('programas.data.0.empresa', 'Acme')
            ->where('programas.data.0.reportes_total', 4)
            ->where('programas.data.0.reportes_por_revisar', 1)
            ->where('programas.data.0.reportes_en_revision', 1)
            ->where('programas.data.0.reportes_aprobados', 1)
            ->where('programas.data.0.reportes_rechazados', 1)
            ->where('resumen.por_revisar', 1));
});

test('el administrador no ve la cola de moderacion: no participa en el dia a dia de los reportes', function () {
    $programa = programaConEmpresa();
    $this->actingAs(administrador());
    reporteDe(investigador(), $programa, ['estado' => 'enviado']);

    $this->get(route('moderacion.index'))->assertForbidden();
});

test('un moderador solo ve en la cola los programas que se le asignaron', function () {
    $suyo = programaConEmpresa(['nombre' => 'Programa Suyo']);
    $ajeno = programaConEmpresa(['nombre' => 'Programa Ajeno']);
    reporteDe(investigador(), $suyo, ['estado' => 'enviado']);
    reporteDe(investigador(), $ajeno, ['estado' => 'enviado']);
    $this->actingAs(moderadorDe($suyo));

    $this->get(route('moderacion.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('programas.data', 1)
            ->where('programas.data.0.nombre', 'Programa Suyo')
            ->has('porRevisar', 1)
            ->where('resumen.por_revisar', 1));

    $this->get(route('moderacion.programa', $ajeno))->assertForbidden();
    $this->get(route('programas.show', $ajeno))->assertForbidden();
});

test('la cola de un programa lista sus informes pendientes con el historial del investigador, sin su identidad', function () {
    $programaModerado = programaConEmpresa();
    $this->actingAs($moderador = moderadorDe($programaModerado));
    $programa = $programaModerado;
    $autor = investigador(['name' => 'Ana Hacker']);
    $pendiente = reporteDe($autor, $programa, ['estado' => 'enviado', 'enviado_en' => now()]);
    reporteDe($autor, $programa, ['estado' => 'rechazado', 'asignado_a' => $moderador->id]);
    reporteDe(investigador(), $programa, ['estado' => 'borrador']);
    reporteDe(investigador(), programaConEmpresa(), ['estado' => 'enviado']);

    $this->get(route('moderacion.programa', $programa))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderacion/Programa')
            ->where('filtro', 'por_revisar')
            ->has('reportes.data', 1)
            ->where('reportes.data.0.id', $pendiente->id)
            ->where('reportes.data.0.investigador.name', Reporte::AUTOR_ANONIMO)
            ->where('reportes.data.0.investigador.id', 0)
            ->where('reportes.data.0.investigador.reportes_descartados', 1)
            ->where('conteos', [
                'por_revisar' => 1,
                'en_revision' => 0,
                'aprobados' => 0,
                'rechazados' => 1,
                'todos' => 2,
            ]));

    $this->get(route('moderacion.programa', [$programa, 'filtro' => 'rechazados']))
        ->assertInertia(fn ($page) => $page->has('reportes.data', 1)->where('reportes.data.0.estado', 'rechazado'));
});

test('otros roles no acceden a la cola de moderacion', function (User $usuario) {
    $this->actingAs($usuario);
    $programa = programaConEmpresa();

    $this->get(route('moderacion.index'))->assertForbidden();
    $this->get(route('moderacion.programa', $programa))->assertForbidden();
})->with([
    'investigador' => fn () => investigador(),
    'empresa' => fn () => miembroDeEmpresa(Empresa::factory()->aprobada()->create()),
]);

test('iniciar la revision cambia el estado y queda en el timeline del investigador', function () {
    $programaModerado = programaConEmpresa();
    $moderador = moderadorDe($programaModerado, ['name' => 'Moderador Luis']);
    $autor = investigador();
    $reporte = reporteDe($autor, $programaModerado, ['estado' => 'enviado', 'asignado_a' => $moderador->id]);

    $this->actingAs($moderador)
        ->post(route('reportes.revisar', $reporte))
        ->assertRedirect(route('reportes.show', $reporte));

    $reporte->refresh();
    expect($reporte->estado->value)->toBe('en_revision')
        ->and($reporte->asignado_a)->toBe($moderador->id);

    $this->actingAs($autor)
        ->get(route('reportes.show', $reporte))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporte.estado', 'en_revision')
            ->where('reporte.eventos.0.tipo', 'cambio_estado')
            ->where('reporte.eventos.0.actor.name', 'Moderador Luis')
            ->where('reporte.eventos.0.metadata.estado_nuevo', 'en_revision'));
});

test('solo se puede iniciar la revision de un informe enviado', function () {
    $programaModerado = programaConEmpresa();
    $reporte = reporteDe(investigador(), $programaModerado, ['estado' => 'validado']);

    // Tras la validación el moderador ya no tría ese informe: el ABAC lo corta antes.
    $this->actingAs(moderadorDe($programaModerado))->post(route('reportes.revisar', $reporte))->assertForbidden();
    $this->actingAs(investigador())->post(route('reportes.revisar', $reporte))->assertForbidden();
});

test('el revisor puede rechazar y penalizar, y el investigador lo ve en su timeline', function () {
    $programaModerado = programaConEmpresa();
    $moderador = moderadorDe($programaModerado);
    $autor = investigador();
    $reporte = reporteDe($autor, $programaModerado, ['estado' => 'en_revision', 'asignado_a' => $moderador->id]);

    $this->actingAs($moderador)
        ->post(route('reportes.rechazar', $reporte), [
            'nota' => 'No se pudo reproducir la vulnerabilidad.',
            'sancionar' => true,
            'gravedad_sancion' => 'leve',
        ])
        ->assertRedirect();

    expect($reporte->fresh()->estado->value)->toBe('rechazado');
    $this->assertDatabaseHas('sanciones', ['usuario_id' => $autor->id, 'reporte_id' => $reporte->id]);

    $this->actingAs($autor)
        ->get(route('reportes.show', $reporte))
        ->assertInertia(fn ($page) => $page
            ->where('reporte.estado', 'rechazado')
            ->where('reporte.eventos.0.descripcion', 'No se pudo reproducir la vulnerabilidad.'));
});

test('el revisor puede marcar un duplicado eligiendo entre los informes del programa', function () {
    $programaModerado = programaConEmpresa();
    $moderador = moderadorDe($programaModerado);
    $programa = $programaModerado;
    $original = reporteDe(investigador(), $programa, ['estado' => 'validado']);
    $duplicado = reporteDe(investigador(), $programa, ['estado' => 'enviado', 'asignado_a' => $moderador->id]);
    reporteDe(investigador(), programaConEmpresa(), ['estado' => 'validado']);
    $this->actingAs($moderador);

    $this->get(route('reportes.show', $duplicado))
        ->assertInertia(fn ($page) => $page
            ->where('puedeModerar', true)
            ->has('candidatosDuplicado', 1)
            ->where('candidatosDuplicado.0.id', $original->id));

    $this->post(route('reportes.marcar-duplicado', $duplicado), ['reporte_duplicado_id' => $original->id])
        ->assertRedirect();

    expect($duplicado->fresh())
        ->estado->value->toBe('duplicado')
        ->es_duplicado_de->toBe($original->id);
});

test('la pagina de moderacion ofrece solo el siguiente informe de la cola: el primero en llegar', function () {
    $programaModerado = programaConEmpresa();
    $this->actingAs(moderadorDe($programaModerado));
    $programa = $programaModerado;
    $reciente = reporteDe(investigador(), $programa, ['estado' => 'enviado', 'enviado_en' => now()]);
    $antiguo = reporteDe(investigador(), $programa, ['estado' => 'enviado', 'enviado_en' => now()->subDay()]);
    reporteDe(investigador(), $programa, ['estado' => 'borrador']);
    reporteDe(investigador(), $programa, ['estado' => 'validado']);

    $this->get(route('moderacion.index'))
        ->assertInertia(fn ($page) => $page
            ->has('porRevisar', 1)
            ->where('porRevisar.0.id', $antiguo->id)
            ->where('porRevisar.0.investigador.reputation_score', fn ($valor) => is_int($valor))
            ->where('resumen.por_revisar', 2));

    // El que llegó después espera su turno: no se puede abrir ni tomar todavía.
    $this->get(route('reportes.show', $reciente))->assertForbidden();
    $this->post(route('reportes.revisar', $reciente))->assertForbidden();

    // Tomado el primero, el siguiente pasa a ser el de la cola.
    $this->post(route('reportes.revisar', $antiguo))->assertRedirect();
    $this->get(route('moderacion.index'))
        ->assertInertia(fn ($page) => $page->has('porRevisar', 1)->where('porRevisar.0.id', $reciente->id));
    $this->get(route('reportes.show', $reciente))->assertOk();
});

test('guardar y enviar deja el informe visible para el moderador y la empresa', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id, 'estado' => EstadoPrograma::Activo, 'es_publico' => true]);
    $autor = investigador();

    $this->actingAs($autor)->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Solo borrador',
        'descripcion' => 'No se envia',
    ])->assertRedirect();
    $this->actingAs($autor)->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Enviado al guardar',
        'descripcion' => 'Se envia',
        'poc' => ['evidencia' => 'Pasos para reproducir el hallazgo.'],
        'enviar' => true,
    ])->assertRedirect();

    $borrador = Reporte::where('titulo', 'Solo borrador')->firstOrFail();
    $enviado = Reporte::where('titulo', 'Enviado al guardar')->firstOrFail();

    expect($borrador->estado->value)->toBe('borrador')
        ->and($enviado->estado->value)->toBe('enviado')
        ->and($enviado->enviado_en)->not->toBeNull()
        ->and($enviado->eventos()->pluck('tipo')->map->value->all())->toContain('creado', 'enviado');

    $this->actingAs(moderadorDe($programa))->get(route('moderacion.index'))
        ->assertInertia(fn ($page) => $page->has('porRevisar', 1)->where('porRevisar.0.id', $enviado->id));

    // La empresa no ve lo que se está revisando: le llega cuando moderación lo aprueba o lo descarta.
    $this->actingAs(miembroDeEmpresa($empresa))->get(route('empresa.reportes'))
        ->assertInertia(fn ($page) => $page->has('reportes.data', 0));

    $enviado->update(['estado' => 'en_revision']);
    $this->get(route('empresa.reportes'))->assertInertia(fn ($page) => $page->has('reportes.data', 0));

    $enviado->update(['estado' => 'validado']);
    $this->get(route('empresa.reportes'))
        ->assertInertia(fn ($page) => $page->has('reportes.data', 1)->where('reportes.data.0.id', $enviado->id));
});

function informeEnviadoConContenido(Programa $programa, User $autor): Reporte
{
    test()->actingAs($autor)->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'XSS en el buscador',
        'descripcion' => 'El parametro q se refleja sin escapar.',
        'categoria' => 'xss',
        'vector_cvss' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N',
        'puntuacion_cvss' => 6.1,
        'poc' => ['pasos' => 'Abrir /buscar?q=<script>alert(1)</script>'],
        'enviar' => true,
    ])->assertRedirect();

    return Reporte::where('titulo', 'XSS en el buscador')->latest('id')->firstOrFail();
}

test('la pagina del informe muestra el contenido descifrado a quien puede verlo', function (string $lector) {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaConEmpresa(['empresa_id' => $empresa->id, 'poc_schema' => [
        ['name' => 'pasos', 'label' => 'Pasos para reproducir', 'type' => 'textarea', 'required' => true],
    ]]);
    $autor = investigador();
    $reporte = informeEnviadoConContenido($programa, $autor);

    $usuario = match ($lector) {
        'moderador' => moderadorDe($programa),
        'administrador' => administrador(),
        'autor' => $autor,
        'empresa duena' => miembroDeEmpresa($empresa),
    };

    // La empresa solo lee (y descifra la PoC de) lo que el moderador ya validó.
    if ($lector === 'empresa duena') {
        $reporte->update(['estado' => 'validado']);
    }

    $this->actingAs($usuario)
        ->get(route('reportes.show', $reporte))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporte.descripcion', 'El parametro q se refleja sin escapar.')
            ->where('reporte.poc.pasos', 'Abrir /buscar?q=<script>alert(1)</script>')
            ->where('reporte.programa.poc_schema.0.label', 'Pasos para reproducir')
            ->where('reporte.categoria', 'xss')
            ->where('cifradoIndisponible', false));
})->with(['moderador', 'administrador', 'autor', 'empresa duena']);

test('la pagina del informe no se entrega a quien no puede verlo', function (string $lector) {
    $programa = programaConEmpresa();
    $reporte = informeEnviadoConContenido($programa, investigador());

    $usuario = match ($lector) {
        'otro investigador' => investigador(),
        'moderador de otro programa' => moderador(),
        'otra empresa' => miembroDeEmpresa(Empresa::factory()->aprobada()->create()),
    };

    $this->actingAs($usuario)->get(route('reportes.show', $reporte))->assertForbidden();
})->with(['otro investigador', 'moderador de otro programa', 'otra empresa']);

test('la pagina del informe indica si el revisor puede iniciar la revision', function () {
    $programa = programaConEmpresa();
    $enviado = informeEnviadoConContenido($programa, investigador());
    $this->actingAs(moderadorDe($enviado));

    $this->get(route('reportes.show', $enviado))->assertInertia(fn ($page) => $page->where('accionesDisponibles.revisar', true));

    $enviado->update(['estado' => 'validado']);
    $this->get(route('reportes.show', $enviado))->assertInertia(fn ($page) => $page->where('accionesDisponibles.revisar', false));
});

test('el revisor ve el alcance del programa y el historial del investigador en el informe', function () {
    $programa = programaConEmpresa(['bugs_buscados' => 'Inyecciones y XSS']);
    $programaModerado = $programa;
    ObjetivoPrograma::factory()->create(['programa_id' => $programa->id, 'tipo' => 'web', 'valor' => 'app.acme.test']);
    $autor = investigador(['reputation_score' => 30]);
    reporteDe($autor, $programa, ['estado' => 'validado']);
    reporteDe($autor, $programa, ['estado' => 'rechazado']);
    $reporte = informeEnviadoConContenido($programa, $autor);
    $moderador = moderadorDe($programaModerado);
    $reporte->update(['estado' => 'en_revision', 'asignado_a' => $moderador->id]);

    $this->actingAs($moderador)->get(route('reportes.show', $reporte))
        ->assertInertia(fn ($page) => $page
            ->where('historialInvestigador', ['reputation_score' => 30, 'informes' => 3, 'aprobados' => 1, 'descartados' => 1])
            ->where('reporte.programa.bugs_buscados', 'Inyecciones y XSS')
            ->where('reporte.programa.objetivos.0.valor', 'app.acme.test')
            ->where('reporte.descripcion', 'El parametro q se refleja sin escapar.'));

    // El autor ve su informe, pero no el historial ni el alcance pensados para revisar.
    $this->actingAs($autor)->get(route('reportes.show', $reporte))
        ->assertInertia(fn ($page) => $page
            ->where('historialInvestigador', null)
            ->missing('reporte.programa.objetivos')
            ->has('reporte.programa.poc_schema'));
});

test('al entrar a un programa el revisor ve sus informes para revisarlos ahi mismo', function () {
    $programaModerado = programaConEmpresa();
    $programa = $programaModerado;
    $enviado = reporteDe(investigador(), $programa, ['estado' => 'enviado', 'enviado_en' => now()]);
    $this->actingAs($moderador = moderadorDe($programaModerado));
    reporteDe(investigador(), $programa, ['estado' => 'validado', 'asignado_a' => $moderador->id]);
    reporteDe(investigador(), $programa, ['estado' => 'rechazado', 'asignado_a' => moderador()->id]);
    reporteDe(investigador(), $programa, ['estado' => 'borrador']);

    $this->get(route('programas.show', $programa))
        ->assertInertia(fn ($page) => $page
            ->where('puedeModerar', true)
            ->where('filtroInformes', 'por_revisar')
            ->has('informes', 1)
            ->where('informes.0.id', $enviado->id)
            ->where('conteosInformes', ['por_revisar' => 1, 'en_revision' => 0, 'aprobados' => 1, 'rechazados' => 1, 'todos' => 3]));

    $this->get(route('programas.show', [$programa, 'filtro' => 'aprobados']))
        ->assertInertia(fn ($page) => $page->has('informes', 1)->where('informes.0.estado', 'validado'));

    // El que decidió otro moderador cuenta en el total, pero no se le muestra.
    $this->get(route('programas.show', [$programa, 'filtro' => 'rechazados']))
        ->assertInertia(fn ($page) => $page->has('informes', 0));
});

test('quien no revisa no recibe los informes del programa', function (string $rol) {
    $programa = programaConEmpresa();
    reporteDe(investigador(), $programa, ['estado' => 'enviado']);

    $usuario = $rol === 'investigador' ? investigador() : miembroDeEmpresa($programa->empresa);

    $this->actingAs($usuario)->get(route('programas.show', $programa))
        ->assertInertia(fn ($page) => $page
            ->where('puedeModerar', false)
            ->where('informes', [])
            ->where('conteosInformes', null));
})->with(['investigador', 'empresa duena']);

test('iniciar la revision desde una lista vuelve a la misma pagina', function () {
    $programaModerado = programaConEmpresa();
    $programa = $programaModerado;
    $reporte = reporteDe(investigador(), $programa, ['estado' => 'enviado']);

    $this->actingAs(moderadorDe($reporte))
        ->from(route('programas.show', $programa))
        ->post(route('reportes.revisar', $reporte))
        ->assertRedirect(route('programas.show', $programa));

    expect($reporte->fresh()->estado->value)->toBe('en_revision');
});

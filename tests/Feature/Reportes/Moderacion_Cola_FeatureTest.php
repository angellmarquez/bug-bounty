<?php

use App\Enums\EstadoPrograma;
use App\Models\Empresa;
use App\Models\Programa;
use App\Models\User;

function programaConEmpresa(array $atributos = []): Programa
{
    return Programa::factory()->create([
        'empresa_id' => Empresa::factory()->aprobada()->create(['nombre_comercial' => 'Acme'])->id,
        'estado' => EstadoPrograma::Activo,
        ...$atributos,
    ]);
}

test('moderadores y administradores ven la cola de moderacion por programa', function (User $usuario) {
    $this->actingAs($usuario);
    $programa = programaConEmpresa(['nombre' => 'Programa Acme']);
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
})->with([
    'moderador' => fn () => moderador(),
    'administrador' => fn () => administrador(),
]);

test('la cola de un programa lista sus informes pendientes con datos del investigador', function () {
    $this->actingAs(moderador());
    $programa = programaConEmpresa();
    $autor = investigador(['name' => 'Ana Hacker']);
    $pendiente = reporteDe($autor, $programa, ['estado' => 'enviado', 'enviado_en' => now()]);
    reporteDe($autor, $programa, ['estado' => 'rechazado']);
    reporteDe(investigador(), $programa, ['estado' => 'borrador']);
    reporteDe(investigador(), programaConEmpresa(), ['estado' => 'enviado']);

    $this->get(route('moderacion.programa', $programa))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderacion/Programa')
            ->where('filtro', 'por_revisar')
            ->has('reportes.data', 1)
            ->where('reportes.data.0.id', $pendiente->id)
            ->where('reportes.data.0.investigador.name', 'Ana Hacker')
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
    'gestion' => fn () => gestion(),
    'empresa' => fn () => miembroDeEmpresa(Empresa::factory()->aprobada()->create()),
]);

test('iniciar la revision cambia el estado y queda en el timeline del investigador', function () {
    $moderador = moderador(['name' => 'Moderador Luis']);
    $autor = investigador();
    $reporte = reporteDe($autor, programaConEmpresa(), ['estado' => 'enviado']);

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
    $reporte = reporteDe(investigador(), programaConEmpresa(), ['estado' => 'validado']);

    $this->actingAs(moderador())->post(route('reportes.revisar', $reporte))->assertUnprocessable();
    $this->actingAs(investigador())->post(route('reportes.revisar', $reporte))->assertForbidden();
});

test('el revisor puede rechazar y penalizar, y el investigador lo ve en su timeline', function () {
    $moderador = moderador();
    $autor = investigador();
    $reporte = reporteDe($autor, programaConEmpresa(), ['estado' => 'en_revision', 'asignado_a' => $moderador->id]);

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
    $moderador = moderador();
    $programa = programaConEmpresa();
    $original = reporteDe(investigador(), $programa, ['estado' => 'validado']);
    $duplicado = reporteDe(investigador(), $programa, ['estado' => 'enviado']);
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

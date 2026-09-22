<?php

use App\Enums\EstadoPrograma;
use App\Models\Empresa;
use App\Models\ObjetivoPrograma;
use App\Models\Programa;
use App\Models\Reporte;
use App\Services\Pgp\PgpService;

function datosDeEdicion(array $extra = []): array
{
    return [
        'nombre' => 'Programa editado',
        'descripcion' => 'Descripcion editada',
        'requiere_poc' => '1',
        'es_publico' => '1',
        'nivel_acceso' => 'bajo',
        ...$extra,
    ];
}

function programaDeEmpresaConInformes(Empresa $empresa): Programa
{
    $programa = conObjetivo(Programa::factory()->create(['empresa_id' => $empresa->id, 'estado' => EstadoPrograma::Activo]));
    $autor = investigador();
    reporteDe($autor, $programa, ['estado' => 'enviado']);
    reporteDe($autor, $programa, ['estado' => 'validado']);
    reporteDe($autor, $programa, ['estado' => 'borrador']);

    return $programa;
}

test('solo la empresa duena puede editar el programa', function (string $rol, bool $permitido) {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaDeEmpresaConInformes($empresa);

    $usuario = match ($rol) {
        'empresa duena' => miembroDeEmpresa($empresa),
        'otra empresa' => miembroDeEmpresa(Empresa::factory()->aprobada()->create()),
        'administrador' => administrador(),
        'moderador' => moderadorDe($programa),
        'investigador' => investigador(),
    };
    $this->actingAs($usuario);

    $editar = $this->get(route('programas.edit', $programa));
    $actualizar = $this->put(route('programas.update', $programa), datosDeEdicion());

    if ($permitido) {
        $editar->assertOk();
        $actualizar->assertRedirect();
        expect($programa->fresh()->nombre)->toBe('Programa editado');
    } else {
        $editar->assertForbidden();
        $actualizar->assertForbidden();
        expect($programa->fresh()->nombre)->not->toBe('Programa editado');
    }
})->with([
    'empresa duena' => ['empresa duena', true],
    'otra empresa' => ['otra empresa', false],
    'administrador' => ['administrador', false],
    'moderador' => ['moderador', false],
    'investigador' => ['investigador', false],
]);

test('la pagina del programa solo ofrece editar a la empresa duena', function (string $rol, bool $puedeEditar) {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaDeEmpresaConInformes($empresa);

    $usuario = match ($rol) {
        'empresa duena' => miembroDeEmpresa($empresa),
        'administrador' => administrador(),
        'moderador' => moderadorDe($programa),
    };

    $this->actingAs($usuario)->get(route('programas.show', $programa))
        ->assertInertia(fn ($page) => $page->where('puedeEditar', $puedeEditar));
})->with([
    'empresa duena' => ['empresa duena', true],
    'administrador' => ['administrador', false],
    'moderador' => ['moderador', false],
]);

test('el administrador no puede publicar, archivar ni editar el programa de una empresa', function () {
    $programa = conObjetivo(Programa::factory()->create(['estado' => EstadoPrograma::Borrador]));
    $this->actingAs(administrador());

    $this->post(route('programas.cambiar-estado', $programa), ['estado' => 'activo'])->assertForbidden();
    expect($programa->fresh()->estado)->toBe(EstadoPrograma::Borrador);
    $this->put(route('programas.update', $programa), datosDeEdicion())->assertForbidden();
});

test('editar un programa conserva sus informes', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = programaDeEmpresaConInformes($empresa);
    $this->actingAs(miembroDeEmpresa($empresa));

    $ids = Reporte::where('programa_id', $programa->id)->orderBy('id')->pluck('id')->all();

    $this->put(route('programas.update', $programa), datosDeEdicion([
        'nombre' => 'Otro nombre que cambia el slug',
        'objetivos' => [['tipo' => 'web', 'valor' => 'nuevo.acme.test']],
    ]))->assertRedirect();

    expect(Reporte::where('programa_id', $programa->id)->orderBy('id')->pluck('id')->all())->toBe($ids)
        ->and(Reporte::withTrashed()->where('programa_id', $programa->id)->count())->toBe(3)
        ->and($programa->fresh()->slug)->toBe('otro-nombre-que-cambia-el-slug');
});

test('editar los objetivos conserva los existentes, crea los nuevos y borra solo los quitados', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    $queSeQueda = ObjetivoPrograma::factory()->create(['programa_id' => $programa->id, 'valor' => 'a.acme.test']);
    $queSeQuita = ObjetivoPrograma::factory()->create(['programa_id' => $programa->id, 'valor' => 'b.acme.test']);
    $this->actingAs(miembroDeEmpresa($empresa));

    $this->put(route('programas.update', $programa), datosDeEdicion(['objetivos' => [
        ['id' => $queSeQueda->id, 'tipo' => 'web', 'valor' => 'a-editado.acme.test'],
        ['tipo' => 'api', 'valor' => 'api.acme.test'],
    ]]))->assertRedirect();

    // El valor queda cifrado en la base: se descifra para comparar el contenido real.
    $pgp = app(PgpService::class);
    $valores = $programa->objetivos()->orderBy('id')->pluck('valor', 'id')
        ->map(fn (string $valor) => $pgp->descifrarObjetivo($valor)['valor'])
        ->all();

    expect($valores)->toHaveCount(2)
        ->and($valores[$queSeQueda->id])->toBe('a-editado.acme.test')
        ->and(array_values($valores))->toContain('api.acme.test')
        ->and(ObjetivoPrograma::find($queSeQuita->id))->toBeNull();
});

test('editar sin tocar los objetivos los deja como estaban', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = conObjetivo(Programa::factory()->create(['empresa_id' => $empresa->id]));
    $objetivoId = $programa->objetivos()->value('id');
    $this->actingAs(miembroDeEmpresa($empresa));

    $this->put(route('programas.update', $programa), datosDeEdicion())->assertRedirect();

    expect($programa->objetivos()->pluck('id')->all())->toBe([$objetivoId]);
});

test('el informe muestra de que programa es, su empresa y el estado del programa', function () {
    $empresa = Empresa::factory()->aprobada()->create(['nombre_comercial' => 'Acme Seguridad']);
    $programa = programaDeEmpresaConInformes($empresa);
    $reporte = Reporte::where('programa_id', $programa->id)->where('estado', 'validado')->firstOrFail();

    $this->actingAs(miembroDeEmpresa($empresa))->get(route('reportes.show', $reporte))
        ->assertInertia(fn ($page) => $page
            ->where('reporte.programa.id', $programa->id)
            ->where('reporte.programa.estado', 'activo')
            ->where('reporte.programa.empresa_nombre', 'Acme Seguridad')
            ->where('reporte.estado', 'validado'));
});

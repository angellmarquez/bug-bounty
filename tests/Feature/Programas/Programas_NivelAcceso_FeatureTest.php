<?php

use App\Models\Empresa;
use App\Models\Programa;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function programaDeNivel(string $nivel): Programa
{
    return conObjetivo(Programa::factory()->create(['estado' => 'activo', 'es_publico' => true, 'nivel_acceso' => $nivel]));
}

test('un investigador solo ve los programas de los niveles que su rango permite', function (int $puntos, array $visibles) {
    $porNivel = [
        'bajo' => programaDeNivel('bajo')->id,
        'medio' => programaDeNivel('medio')->id,
        'alto' => programaDeNivel('alto')->id,
    ];
    $this->actingAs(investigador(['reputation_score' => $puntos]));

    $ids = collect($this->get(route('programas.index'))->inertiaProps()['programas']['data'])->pluck('id')->all();

    foreach ($porNivel as $nivel => $id) {
        expect(in_array($id, $ids, true))->toBe(in_array($nivel, $visibles, true), "nivel {$nivel} con {$puntos} pts");
    }
})->with([
    'bronce' => [0, ['bajo']],
    'plata' => [100, ['bajo', 'medio']],
    'oro' => [300, ['bajo', 'medio', 'alto']],
    'diamante' => [1600, ['bajo', 'medio', 'alto']],
]);

test('no se puede reportar en un programa cuyo nivel supera el rango del investigador', function () {
    $alto = programaDeNivel('alto');
    $this->actingAs(investigador(['reputation_score' => 150]));

    $this->post(route('reportes.store'), ['programa_id' => $alto->id, 'titulo' => 'x', 'descripcion' => 'y'])->assertForbidden();
});

test('abrir un programa de rango superior explica qué rango pide en lugar de un 403 seco', function () {
    $alto = programaDeNivel('alto');
    $this->actingAs(investigador(['reputation_score' => 150]));

    $this->get(route('programas.show', $alto))
        ->assertRedirect(route('programas.index'))
        ->assertSessionHas('error', fn (string $mensaje) => str_contains($mensaje, 'Oro') && str_contains($mensaje, '300'));
});

test('un programa privado o ajeno sigue dando 403 y no revela su nivel', function () {
    $privado = conObjetivo(Programa::factory()->create(['estado' => 'activo', 'es_publico' => false, 'nivel_acceso' => 'alto']));
    $this->actingAs(investigador(['reputation_score' => 0]));

    $this->get(route('programas.show', $privado))->assertForbidden();
});

test('subir de rango abre el acceso a los programas de ese nivel', function () {
    $alto = programaDeNivel('alto');
    $inv = investigador(['reputation_score' => 150]);
    $this->actingAs($inv)->get(route('programas.show', $alto))->assertRedirect(route('programas.index'));

    $inv->forceFill(['reputation_score' => 300])->save();

    $this->actingAs($inv->fresh())->get(route('programas.show', $alto))->assertOk();
});

test('el formulario de reporte solo ofrece los programas accesibles', function () {
    $bajo = programaDeNivel('bajo');
    $alto = programaDeNivel('alto');
    $this->actingAs(investigador(['reputation_score' => 0]));

    $ids = collect($this->get(route('reportes.create'))->inertiaProps()['programas'])->pluck('id')->all();

    expect($ids)->toContain($bajo->id)->not->toContain($alto->id);
});

test('la empresa elige el nivel de acceso al crear y editar un programa', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $this->actingAs(miembroDeEmpresa($empresa));
    $datos = [
        'nombre' => 'Programa con nivel',
        'descripcion' => 'Descripción',
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.test']],
        'nivel_acceso' => 'alto',
    ];

    $this->post(route('programas.store'), $datos)->assertRedirect();
    $programa = Programa::where('nombre', 'Programa con nivel')->firstOrFail();
    expect($programa->nivel_acceso->value)->toBe('alto');

    $this->put(route('programas.update', $programa), [...$datos, 'nivel_acceso' => 'medio'])->assertRedirect();
    expect($programa->fresh()->nivel_acceso->value)->toBe('medio');
});

test('un programa sin nivel indicado queda con acceso bajo y un nivel inválido se rechaza', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $this->actingAs(miembroDeEmpresa($empresa));
    $datos = ['nombre' => 'Sin nivel', 'descripcion' => 'x', 'objetivos' => [['tipo' => 'web', 'valor' => 'app.test']]];

    $this->post(route('programas.store'), $datos)->assertRedirect();
    expect(Programa::where('nombre', 'Sin nivel')->firstOrFail()->nivel_acceso->value)->toBe('bajo');

    $this->post(route('programas.store'), [...$datos, 'nombre' => 'Nivel raro', 'nivel_acceso' => 'legendario'])->assertSessionHasErrors('nivel_acceso');
});

test('ya no existen las rutas ni los campos de pago', function () {
    expect(Route::has('reportes.pagar'))->toBeFalse();

    $this->actingAs(administrador());
    $this->post('/reportes/1/pagar', ['recompensa' => 100])->assertNotFound();
    expect(Schema::hasColumn('reportes', 'recompensa'))->toBeFalse()
        ->and(Schema::hasColumn('programas', 'recompensa_min'))->toBeFalse()
        ->and(Schema::hasColumn('programas', 'moneda'))->toBeFalse()
        ->and(Schema::hasColumn('programas', 'reputacion_minima'))->toBeFalse();
});

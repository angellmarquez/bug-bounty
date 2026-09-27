<?php

use App\Models\Empresa;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Reportes\DetectorDuplicados;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Programa con un informe asignado a su moderador, listo para triar.
 *
 * @return array{programa: Programa, moderador: User, reporte: Reporte}
 */
function informeEnTriaje(array $atributos = []): array
{
    $programa = Programa::factory()->create(['empresa_id' => Empresa::factory()->aprobada()->create()->id]);
    $moderador = moderador();
    $moderador->programasModerados()->attach($programa->id);
    $reporte = reporteDe(investigador(), $programa, [
        'estado' => 'en_revision',
        'asignado_a' => $moderador->id,
        'enviado_en' => now()->subHour(),
        ...$atributos,
    ]);

    return ['programa' => $programa, 'moderador' => $moderador, 'reporte' => $reporte];
}

test('el moderador recibe fichas comparativas: solo informes anteriores no descartados y sin datos del autor', function () {
    ['programa' => $programa, 'moderador' => $moderador, 'reporte' => $reporte] = informeEnTriaje([
        'titulo' => 'Inyección SQL en búsqueda pública', 'categoria' => 'Inyección SQL',
    ]);

    $validado = reporteDe(investigador(), $programa, ['estado' => 'validado', 'titulo' => 'SQL Injection en /api/buscar', 'categoria' => 'Inyección SQL', 'enviado_en' => now()->subDays(3)]);
    $pendiente = reporteDe(investigador(), $programa, ['estado' => 'enviado', 'titulo' => 'Falta cabecera CSP', 'enviado_en' => now()->subDays(2)]);
    reporteDe(investigador(), $programa, ['estado' => 'rechazado', 'enviado_en' => now()->subDays(4)]);
    reporteDe(investigador(), $programa, ['estado' => 'fuera_de_alcance', 'enviado_en' => now()->subDays(4)]);
    reporteDe(investigador(), $programa, ['estado' => 'duplicado', 'enviado_en' => now()->subDays(4)]);
    reporteDe(investigador(), $programa, ['estado' => 'validado', 'enviado_en' => now()]); // posterior
    reporteDe(investigador(), Programa::factory()->create(), ['estado' => 'validado', 'enviado_en' => now()->subDays(5)]); // otro programa

    $this->actingAs($moderador)->get(route('reportes.show', $reporte))
        ->assertInertia(fn (Assert $page) => $page
            ->has('candidatosDuplicado', 2)
            ->where('candidatosDuplicado.0.id', $validado->id)
            ->where('candidatosDuplicado.0.sugerido', true)
            ->where('candidatosDuplicado.0.aprobado', true)
            ->where('candidatosDuplicado.0.motivos', fn ($motivos) => collect($motivos)->contains('Misma categoría'))
            ->missing('candidatosDuplicado.0.investigador_id')
            ->missing('candidatosDuplicado.0.investigador')
            ->missing('candidatosDuplicado.0.descripcion')
            ->missing('candidatosDuplicado.0.poc')
            ->where('candidatosDuplicado.1.id', $pendiente->id)
            ->where('candidatosDuplicado.1.sugerido', false)
            ->has('posiblesDuplicados', 1)
            ->where('posiblesDuplicados.0.id', $validado->id));
});

test('la empresa no puede marcar duplicados ni ve candidatos de informes del programa', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $programa = Programa::factory()->create(['empresa_id' => $empresa->id]);
    reporteDe(investigador(), $programa, ['estado' => 'enviado', 'titulo' => 'Informe aún sin revisar', 'enviado_en' => now()->subDays(3)]);
    $original = reporteDe(investigador(), $programa, ['estado' => 'validado', 'enviado_en' => now()->subDays(2)]);
    $validado = reporteDe(investigador(), $programa, ['estado' => 'validado', 'enviado_en' => now()->subDay()]);
    $this->actingAs(miembroDeEmpresa($empresa));

    $this->get(route('reportes.show', $validado))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accionesDisponibles.marcar_duplicado', false)
            ->has('candidatosDuplicado', 0)
            ->has('posiblesDuplicados', 0));

    $this->post(route('reportes.marcar-duplicado', $validado), ['reporte_duplicado_id' => $original->id])
        ->assertForbidden();

    expect($validado->fresh()->estado->value)->toBe('validado');
});

test('el autor del informe no recibe candidatos a duplicado', function () {
    ['reporte' => $reporte, 'programa' => $programa] = informeEnTriaje();
    reporteDe(investigador(), $programa, ['estado' => 'validado', 'enviado_en' => now()->subDays(2)]);

    $this->actingAs($reporte->investigador)->get(route('reportes.show', $reporte))
        ->assertInertia(fn (Assert $page) => $page->has('candidatosDuplicado', 0)->has('posiblesDuplicados', 0));
});

test('el original no puede estar descartado ni ser un borrador', function (string $estado) {
    ['programa' => $programa, 'moderador' => $moderador, 'reporte' => $reporte] = informeEnTriaje();
    $original = reporteDe(investigador(), $programa, ['estado' => $estado, 'enviado_en' => now()->subDays(2)]);

    $this->actingAs($moderador)
        ->post(route('reportes.marcar-duplicado', $reporte), ['reporte_duplicado_id' => $original->id])
        ->assertStatus(422);

    expect($reporte->fresh()->estado->value)->toBe('en_revision');
})->with(['borrador', 'rechazado', 'fuera_de_alcance', 'duplicado']);

test('el original debe ser del mismo programa', function () {
    ['moderador' => $moderador, 'reporte' => $reporte] = informeEnTriaje();
    $deOtroPrograma = reporteDe(investigador(), Programa::factory()->create(), ['estado' => 'validado', 'enviado_en' => now()->subDays(2)]);

    $this->actingAs($moderador)
        ->post(route('reportes.marcar-duplicado', $reporte), ['reporte_duplicado_id' => $deOtroPrograma->id])
        ->assertStatus(422);
});

test('se puede marcar duplicado de un original aprobado o aun pendiente', function (string $estado) {
    ['programa' => $programa, 'moderador' => $moderador, 'reporte' => $reporte] = informeEnTriaje();
    $original = reporteDe(investigador(), $programa, ['estado' => $estado, 'enviado_en' => now()->subDays(2)]);

    $this->actingAs($moderador)
        ->post(route('reportes.marcar-duplicado', $reporte), ['reporte_duplicado_id' => $original->id])
        ->assertRedirect();

    expect($reporte->fresh())->estado->value->toBe('duplicado')->es_duplicado_de->toBe($original->id);
})->with(['validado', 'en_reparacion', 'cerrado', 'enviado', 'en_revision']);

test('el detector no sugiere solo por compartir categoria: hace falta algo mas', function () {
    $detector = app(DetectorDuplicados::class);
    $base = ['programa_id' => 1, 'severidad' => 'alta'];

    $revisado = new Reporte([...$base, 'titulo' => 'XSS almacenado en comentarios', 'categoria' => 'XSS', 'vector_cvss' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N']);
    $mismaCategoria = new Reporte([...$base, 'titulo' => 'Cookie sin atributo Secure', 'categoria' => 'XSS', 'vector_cvss' => null]);
    $mismoFallo = new Reporte([...$base, 'titulo' => 'XSS almacenado en el perfil', 'categoria' => 'XSS', 'vector_cvss' => null]);
    $mismoVector = new Reporte([...$base, 'titulo' => 'Script inyectado en comentarios públicos', 'categoria' => 'XSS', 'vector_cvss' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N']);

    expect($detector->comparar($mismaCategoria, $revisado)['puntuacion'])->toBeLessThan(DetectorDuplicados::UMBRAL_SUGERENCIA)
        ->and($detector->comparar($mismoFallo, $revisado)['puntuacion'])->toBeGreaterThanOrEqual(DetectorDuplicados::UMBRAL_SUGERENCIA)
        ->and($detector->comparar($mismoFallo, $revisado)['motivos'])->toContain('Misma categoría')
        ->and($detector->comparar($mismoVector, $revisado)['puntuacion'])->toBeGreaterThanOrEqual(DetectorDuplicados::UMBRAL_SUGERENCIA)
        ->and($detector->comparar($mismoVector, $revisado)['motivos'])->toContain('Mismo vector CVSS');
});

test('un candidato sin severidad todavia no rompe la ficha', function () {
    ['programa' => $programa, 'moderador' => $moderador, 'reporte' => $reporte] = informeEnTriaje(['severidad' => null]);
    $original = reporteDe(investigador(), $programa, ['estado' => 'enviado', 'severidad' => null, 'enviado_en' => now()->subDays(2)]);

    $this->actingAs($moderador)->get(route('reportes.show', $reporte))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('candidatosDuplicado.0.id', $original->id)
            ->where('candidatosDuplicado.0.severidad', null));
});

test('el detector ignora acentos y mayusculas al comparar titulos', function () {
    $detector = app(DetectorDuplicados::class);
    $a = new Reporte(['titulo' => 'INYECCIÓN SQL en login', 'categoria' => 'Inyección SQL', 'severidad' => 'critica']);
    $b = new Reporte(['titulo' => 'inyeccion sql en el login de administradores', 'categoria' => 'inyeccion sql', 'severidad' => 'critica']);

    $resultado = $detector->comparar($a, $b);

    expect($resultado['puntuacion'])->toBeGreaterThanOrEqual(DetectorDuplicados::UMBRAL_SUGERENCIA)
        ->and($resultado['motivos'])->toContain('Misma categoría');
});

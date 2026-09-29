<?php

use App\Models\Auditoria;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;

const VECTOR_INVESTIGADOR = 'CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:N/A:N'; // 6.5 media

const VECTOR_CORREGIDO = 'CVSS:3.1/AV:N/AC:H/PR:L/UI:N/S:U/C:L/I:N/A:N'; // 3.1 baja

/** Un informe en revisión, asignado a un moderador del programa. */
function informeEnRevision(): array
{
    $programa = Programa::factory()->create();
    $moderador = moderadorDe($programa);
    $reporte = reporteDe(investigador(), $programa, [
        'estado' => 'en_revision',
        'asignado_a' => $moderador->id,
        'vector_cvss' => VECTOR_INVESTIGADOR,
        'puntuacion_cvss' => 6.5,
        'severidad' => 'media',
    ]);

    return [$moderador, $reporte];
}

test('el moderador asignado ajusta el CVSS y el servidor recalcula puntuacion y severidad', function () {
    [$moderador, $reporte] = informeEnRevision();

    $this->actingAs($moderador)->post(route('reportes.ajustar-cvss', $reporte), [
        'vector_cvss' => VECTOR_CORREGIDO,
        'nota' => 'La explotación requiere condiciones de carrera: AC pasa a Alta.',
        // Se ignora: la nota la calcula el servidor.
        'puntuacion_cvss' => 9.9,
    ])->assertRedirect(route('reportes.show', $reporte))->assertSessionHasNoErrors();

    $reporte->refresh();
    expect($reporte->vector_cvss)->toBe(VECTOR_CORREGIDO)
        ->and((float) $reporte->puntuacion_cvss)->toBe(3.1)
        ->and($reporte->severidad->value)->toBe('baja');
});

test('el ajuste queda en la linea de tiempo y en la auditoria con el valor anterior', function () {
    [$moderador, $reporte] = informeEnRevision();

    $this->actingAs($moderador)->post(route('reportes.ajustar-cvss', $reporte), [
        'vector_cvss' => VECTOR_CORREGIDO,
        'nota' => 'Impacto de confidencialidad limitado.',
    ]);

    $evento = $reporte->eventos()->where('tipo', 'cvss_ajustado')->firstOrFail();
    expect($evento->actor_id)->toBe($moderador->id)
        ->and($evento->nota)->toBe('Impacto de confidencialidad limitado.')
        ->and($evento->datos['anterior'])->toMatchArray(['vector' => VECTOR_INVESTIGADOR, 'puntuacion' => 6.5, 'severidad' => 'media'])
        ->and($evento->datos['nuevo'])->toMatchArray(['vector' => VECTOR_CORREGIDO, 'puntuacion' => 3.1, 'severidad' => 'baja']);

    expect(Auditoria::query()->where('accion', 'reportes.cvss_ajustado')->where('usuario_id', $moderador->id)->exists())->toBeTrue();
});

test('el detalle ofrece ajustar CVSS solo al moderador asignado', function () {
    [$moderador, $reporte] = informeEnRevision();

    $this->actingAs($moderador)->get(route('reportes.show', $reporte))
        ->assertInertia(fn ($page) => $page->where('accionesDisponibles.ajustar_cvss', true));

    $this->actingAs($reporte->investigador)->get(route('reportes.show', $reporte))
        ->assertInertia(fn ($page) => $page->where('accionesDisponibles.ajustar_cvss', false));
});

test('no pueden ajustar el CVSS el autor, un moderador no asignado ni el administrador', function (string $quien) {
    [, $reporte] = informeEnRevision();

    $actor = match ($quien) {
        'autor' => $reporte->investigador,
        'otro moderador' => moderadorDe($reporte->programa),
        'administrador' => administrador(),
    };

    $this->actingAs($actor)->post(route('reportes.ajustar-cvss', $reporte), [
        'vector_cvss' => VECTOR_CORREGIDO,
        'nota' => 'Intento no autorizado.',
    ])->assertForbidden();

    expect($reporte->fresh()->vector_cvss)->toBe(VECTOR_INVESTIGADOR);
})->with(['autor', 'otro moderador', 'administrador']);

test('no se ajusta el CVSS de un informe ya validado', function () {
    [$moderador, $reporte] = informeEnRevision();
    $reporte->update(['estado' => 'validado']);

    $this->actingAs($moderador)->post(route('reportes.ajustar-cvss', $reporte), [
        'vector_cvss' => VECTOR_CORREGIDO,
        'nota' => 'Demasiado tarde.',
    ])->assertForbidden();
});

test('rechaza un vector invalido, uno sin cambios o sin motivo', function (array $datos, string $campo) {
    [$moderador, $reporte] = informeEnRevision();

    $this->actingAs($moderador)->post(route('reportes.ajustar-cvss', $reporte), $datos)
        ->assertSessionHasErrors($campo);

    expect($reporte->fresh()->vector_cvss)->toBe(VECTOR_INVESTIGADOR)
        ->and(Reporte::query()->whereKey($reporte->id)->first()->eventos()->where('tipo', 'cvss_ajustado')->exists())->toBeFalse();
})->with([
    'vector inventado' => [['vector_cvss' => 'CVSS:3.1/AV:X/AC:L/PR:L/UI:N/S:U/C:H/I:N/A:N', 'nota' => 'x'], 'vector_cvss'],
    'mismo vector' => [['vector_cvss' => VECTOR_INVESTIGADOR, 'nota' => 'x'], 'vector_cvss'],
    'sin motivo' => [['vector_cvss' => VECTOR_CORREGIDO, 'nota' => ''], 'nota'],
]);

test('el investigador recibe aviso del ajuste', function () {
    [$moderador, $reporte] = informeEnRevision();
    /** @var User $autor */
    $autor = $reporte->investigador;

    $this->actingAs($moderador)->post(route('reportes.ajustar-cvss', $reporte), [
        'vector_cvss' => VECTOR_CORREGIDO,
        'nota' => 'Ajuste de severidad.',
    ]);

    expect($autor->fresh()->notifications()->count())->toBeGreaterThan(0);
});

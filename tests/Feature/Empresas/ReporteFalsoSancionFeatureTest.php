<?php

use App\Enums\GravedadSancion;
use App\Models\Sancion;

test('moderator can reject a false report and apply a proportional sanction', function () {
    $moderador = investigador();
    $moderador->roles()->syncWithoutDetaching([rol('moderador')->id]);
    $programa = programaDe(administrador());
    $programa->moderadores()->attach($moderador);
    $investigador = investigador();
    $reporte = reporteDe($investigador, $programa, ['estado' => 'enviado']);
    $this->actingAs($moderador);

    $this->post(route('reportes.rechazar', $reporte), [
        'nota' => 'La evidencia fue fabricada.',
        'sancionar' => true,
        'gravedad_sancion' => 'media',
    ])->assertRedirect();

    $sancion = Sancion::where('reporte_id', $reporte->id)->firstOrFail();
    expect($sancion->usuario_id)->toBe($investigador->id)
        ->and($sancion->gravedad)->toBe(GravedadSancion::Media)
        ->and($sancion->puntos)->toBeLessThan(0);
});

test('rejecting a report without false flag does not sanction researcher', function () {
    $moderador = investigador();
    $moderador->roles()->syncWithoutDetaching([rol('moderador')->id]);
    $programa = programaDe(administrador());
    $programa->moderadores()->attach($moderador);
    $investigador = investigador();
    $reporte = reporteDe($investigador, $programa, ['estado' => 'enviado']);
    $this->actingAs($moderador);

    $this->post(route('reportes.rechazar', $reporte), [
        'nota' => 'Fuera de alcance.',
    ])->assertRedirect();

    expect(Sancion::where('reporte_id', $reporte->id)->exists())->toBeFalse();
});

<?php

use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;

/**
 * La recompensa es para quien encontró la vulnerabilidad primero: el moderador valida y la
 * empresa confirma (momento en que se dan los puntos) por orden de llegada dentro del programa.
 *
 * @return array{propietario: User, programa: Programa, moderador: User, primero: Reporte, segundo: Reporte}
 */
function colaDeDos(string $estadoPrimero = 'enviado', string $estadoSegundo = 'enviado'): array
{
    $propietario = propietarioDeEmpresa();
    $programa = programaDeEmpresa($propietario);
    $moderador = moderadorDe($programa);

    // El administrador le asignó ambos informes: el moderador solo tría los que tiene asignados.
    return [
        'propietario' => $propietario,
        'programa' => $programa,
        'moderador' => $moderador,
        'primero' => reporteDe(investigador(), $programa, ['estado' => $estadoPrimero, 'enviado_en' => now()->subHours(2), 'asignado_a' => $moderador->id]),
        'segundo' => reporteDe(investigador(), $programa, ['estado' => $estadoSegundo, 'enviado_en' => now()->subHour(), 'asignado_a' => $moderador->id]),
    ];
}

test('el moderador no puede validar un informe mientras uno anterior del programa siga sin triar', function (string $estadoDelPrimero) {
    ['moderador' => $moderador, 'primero' => $primero, 'segundo' => $segundo] = colaDeDos($estadoDelPrimero);

    $this->actingAs($moderador)->post(route('reportes.validar', $segundo))->assertSessionHasErrors('estado');

    expect($segundo->fresh()->estado->value)->toBe('enviado');
})->with(['enviado', 'en_revision', 'needs_info']);

test('resuelto el anterior, el siguiente ya se puede validar', function () {
    ['moderador' => $moderador, 'primero' => $primero, 'segundo' => $segundo] = colaDeDos();
    $this->actingAs($moderador);

    $this->post(route('reportes.validar', $primero))->assertSessionHasNoErrors();
    $this->post(route('reportes.validar', $segundo))->assertSessionHasNoErrors();

    expect($segundo->fresh()->estado->value)->toBe('validado');
});

test('rechazar o marcar duplicado no espera turno porque no da puntos', function () {
    ['moderador' => $moderador, 'primero' => $primero, 'segundo' => $segundo] = colaDeDos();
    $this->actingAs($moderador);

    $this->post(route('reportes.marcar-duplicado', $segundo), ['reporte_duplicado_id' => $primero->id])->assertSessionHasNoErrors();

    expect($segundo->fresh()->estado->value)->toBe('duplicado');
});

test('un informe de otro programa no bloquea la cola', function () {
    ['moderador' => $moderador, 'programa' => $programa] = colaDeDos();
    reporteDe(investigador(), programaDeEmpresa(propietarioDeEmpresa()), ['estado' => 'enviado', 'enviado_en' => now()->subDays(3)]);
    $solo = reporteDe(investigador(), $programa, ['estado' => 'enviado', 'enviado_en' => now()->subDays(2), 'asignado_a' => $moderador->id]);

    $this->actingAs($moderador)->post(route('reportes.validar', $solo))->assertRedirect()->assertSessionHasNoErrors();
    expect($solo->fresh()->estado->value)->toBe('validado');
});

test('la pagina no ofrece validar fuera de turno y explica cual va primero', function () {
    ['moderador' => $moderador, 'primero' => $primero, 'segundo' => $segundo] = colaDeDos();

    $this->actingAs($moderador)->get(route('reportes.show', $segundo))
        ->assertInertia(fn ($page) => $page
            ->where('accionesDisponibles.validar', false)
            ->where('esperaTurno', fn ($mensaje) => str_contains($mensaje, $primero->numero_reporte)));

    $this->get(route('reportes.show', $primero))
        ->assertInertia(fn ($page) => $page->where('accionesDisponibles.validar', true)->where('esperaTurno', null));
});

test('la empresa confirma por orden y solo al confirmar gana los puntos el investigador', function () {
    ['propietario' => $propietario, 'primero' => $primero, 'segundo' => $segundo] = colaDeDos('validado', 'validado');
    $this->actingAs($propietario);

    $this->post(route('reportes.reparacion', $segundo))->assertSessionHasErrors('estado');
    $this->post(route('reportes.cerrar', $segundo))->assertSessionHasErrors('estado');
    expect($segundo->fresh()->estado->value)->toBe('validado')
        ->and($segundo->investigador->fresh()->reputation_score)->toBe(0);

    $this->post(route('reportes.reparacion', $primero))->assertSessionHasNoErrors();
    expect($primero->investigador->fresh()->reputation_score)->toBe((int) config('reputacion.puntos.reporte_validado'));

    $this->post(route('reportes.reparacion', $segundo))->assertSessionHasNoErrors();
    expect($segundo->investigador->fresh()->reputation_score)->toBe((int) config('reputacion.puntos.reporte_validado'));
});

test('la empresa tampoco confirma mientras haya un informe anterior aun sin triar, y no se le revela cual', function () {
    ['propietario' => $propietario, 'primero' => $primero, 'segundo' => $segundo] = colaDeDos('enviado', 'validado');

    $this->actingAs($propietario)->get(route('reportes.show', $segundo))
        ->assertInertia(fn ($page) => $page
            ->where('accionesDisponibles.reparacion', false)
            ->where('accionesDisponibles.cerrar', false)
            ->where('esperaTurno', fn ($mensaje) => str_contains($mensaje, 'un informe anterior de este programa')
                && ! str_contains($mensaje, $primero->numero_reporte)));

    $this->post(route('reportes.reparacion', $segundo))->assertSessionHasErrors('estado');
});

test('pasar de en reparacion a cerrado no vuelve a dar los puntos de la confirmacion', function () {
    ['propietario' => $propietario, 'primero' => $primero] = colaDeDos('validado', 'rechazado');
    $this->actingAs($propietario);

    $this->post(route('reportes.reparacion', $primero))->assertSessionHasNoErrors();
    $this->post(route('reportes.cerrar', $primero))->assertSessionHasNoErrors();

    expect($primero->investigador->fresh()->reputation_score)
        ->toBe((int) config('reputacion.puntos.reporte_validado') + (int) config('reputacion.puntos.reporte_resuelto'));
});

test('reenviar tras needs_info conserva el turno del primer envio', function () {
    $programa = conObjetivo(Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]));
    $autor = investigador();
    $primerEnvio = now()->subDay()->startOfSecond();
    $reporte = reporteDe($autor, $programa, [
        'estado' => 'needs_info',
        'enviado_en' => $primerEnvio,
        'poc' => pocCifrado(['evidencia' => 'Pasos para reproducir.']),
    ]);

    $this->actingAs($autor)->post(route('reportes.enviar', $reporte))->assertSessionHasNoErrors();

    expect($reporte->fresh()->estado->value)->toBe('enviado')
        ->and($reporte->fresh()->enviado_en->equalTo($primerEnvio))->toBeTrue();
});

test('cuenta la fecha de envio, no la del borrador: un borrador viejo enviado tarde no es el original', function () {
    ['moderador' => $moderador, 'programa' => $programa] = colaDeDos();
    $enviadoPrimero = reporteDe(investigador(), $programa, ['estado' => 'enviado', 'enviado_en' => now()->subHours(5), 'asignado_a' => $moderador->id]);
    $borradorViejo = reporteDe(investigador(), $programa, ['estado' => 'enviado', 'enviado_en' => now()->subHours(4)]);
    $borradorViejo->forceFill(['created_at' => now()->subDays(10)])->save();

    $this->actingAs($moderador)
        ->post(route('reportes.marcar-duplicado', $enviadoPrimero), ['reporte_duplicado_id' => $borradorViejo->id])
        ->assertStatus(422);

    expect($enviadoPrimero->fresh()->estado->value)->toBe('enviado');
});

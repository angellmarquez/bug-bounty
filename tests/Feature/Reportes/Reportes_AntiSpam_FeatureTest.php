<?php

use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Reputacion\CheatDetectionService;

/** Deja `$cantidad` informes ya enviados por `$investigador` a `$programa`, hace `$haceMinutos`. */
function enviosPrevios(User $investigador, Programa $programa, int $cantidad, int $haceMinutos = 1, string $prefijo = 'Previo'): void
{
    foreach (range(1, $cantidad) as $i) {
        reporteDe($investigador, $programa, [
            'estado' => 'enviado',
            'enviado_en' => now()->subMinutes($haceMinutos),
            'titulo' => "{$prefijo} {$i}",
        ]);
    }
}

function borradorDe(User $investigador, Programa $programa, string $titulo = 'Hallazgo nuevo'): Reporte
{
    return reporteDe($investigador, $programa, ['estado' => 'borrador', 'enviado_en' => null, 'titulo' => $titulo]);
}

function enviadosDe(User $investigador): int
{
    return Reporte::query()->where('investigador_id', $investigador->id)->whereNotNull('enviado_en')->count();
}

// ---------------------------------------------------------------------------
// Reglas por separado
// ---------------------------------------------------------------------------

test('por debajo del limite el investigador envia con normalidad', function () {
    $user = investigador();
    $this->actingAs($user);
    $programa = Programa::factory()->create();
    enviosPrevios($user, $programa, 2);
    $borrador = borradorDe($user, $programa);

    $this->post(route('reportes.enviar', $borrador))->assertRedirect();

    expect($borrador->fresh()->estado->value)->toBe('enviado')
        ->and($borrador->fresh()->enviado_en)->not->toBeNull();
});

test('bloquea otro envio al mismo programa cuando ya envio el maximo en la ventana', function () {
    $user = investigador();
    $this->actingAs($user);
    $programa = Programa::factory()->create(['nombre' => 'Programa Acme']);
    enviosPrevios($user, $programa, 3);
    $borrador = borradorDe($user, $programa);

    $respuesta = $this->post(route('reportes.enviar', $borrador));

    $respuesta->assertRedirect(route('reportes.show', $borrador));
    $respuesta->assertSessionHas('error', fn (?string $mensaje) => str_contains((string) $mensaje, 'Ya enviaste 3 informes a Programa Acme')
        && str_contains((string) $mensaje, 'aproximadamente'));
    expect($borrador->fresh()->estado->value)->toBe('borrador')
        ->and($borrador->fresh()->enviado_en)->toBeNull();
    $this->assertDatabaseMissing('eventos_reporte', ['reporte_id' => $borrador->id, 'tipo' => 'enviado']);
});

test('bloquea el envio en total aunque vayan a programas distintos', function () {
    $user = investigador();
    $this->actingAs($user);

    foreach (range(1, 5) as $i) {
        enviosPrevios($user, Programa::factory()->create(), 1, 2, "Otro programa {$i}");
    }
    $borrador = borradorDe($user, Programa::factory()->create());

    $this->post(route('reportes.enviar', $borrador))
        ->assertSessionHas('error', fn (?string $mensaje) => str_contains((string) $mensaje, 'Has enviado 5 informes en los últimos 15 minutos'));

    expect($borrador->fresh()->estado->value)->toBe('borrador');
});

test('bloquea un titulo repetido en el mismo programa aunque cambien mayusculas y espacios', function () {
    $user = investigador();
    $this->actingAs($user);
    $programa = Programa::factory()->create();
    // Hace 2 horas: fuera de la ventana por programa, dentro de las 24 h del titulo repetido.
    reporteDe($user, $programa, ['estado' => 'enviado', 'enviado_en' => now()->subHours(2), 'titulo' => 'XSS en login']);
    $borrador = borradorDe($user, $programa, '  xss EN login ');

    $this->post(route('reportes.enviar', $borrador))
        ->assertSessionHas('error', fn (?string $mensaje) => str_contains((string) $mensaje, 'mismo título'));

    expect($borrador->fresh()->estado->value)->toBe('borrador');
});

test('el mismo titulo en otro programa si se puede enviar', function () {
    $user = investigador();
    $this->actingAs($user);
    reporteDe($user, Programa::factory()->create(), ['estado' => 'enviado', 'enviado_en' => now()->subHours(2), 'titulo' => 'XSS en login']);
    $borrador = borradorDe($user, Programa::factory()->create(), 'XSS en login');

    $this->post(route('reportes.enviar', $borrador))->assertSessionMissing('error');

    expect($borrador->fresh()->estado->value)->toBe('enviado');
});

test('los borradores no cuentan para el limite', function () {
    $user = investigador();
    $this->actingAs($user);
    $programa = Programa::factory()->create();
    foreach (range(1, 10) as $i) {
        borradorDe($user, $programa, "Borrador {$i}");
    }
    $borrador = borradorDe($user, $programa, 'El que se envia');

    $this->post(route('reportes.enviar', $borrador))->assertSessionMissing('error');

    expect($borrador->fresh()->estado->value)->toBe('enviado');
});

test('el limite es por investigador: otro investigador puede enviar al mismo programa', function () {
    $spammer = investigador();
    $otro = investigador();
    $programa = Programa::factory()->create();
    enviosPrevios($spammer, $programa, 3);
    $borradorDelOtro = borradorDe($otro, $programa);

    $this->actingAs($otro)->post(route('reportes.enviar', $borradorDelOtro))->assertSessionMissing('error');

    expect($borradorDelOtro->fresh()->estado->value)->toBe('enviado');
});

// ---------------------------------------------------------------------------
// Ventanas de tiempo
// ---------------------------------------------------------------------------

test('lo enviado hace mas de la ventana ya no cuenta', function () {
    $user = investigador();
    $this->actingAs($user);
    $programa = Programa::factory()->create();
    enviosPrevios($user, $programa, 3, haceMinutos: 70);
    $borrador = borradorDe($user, $programa);

    $this->post(route('reportes.enviar', $borrador))->assertSessionMissing('error');

    expect($borrador->fresh()->estado->value)->toBe('enviado');
});

test('tras esperar la ventana el investigador vuelve a poder enviar', function () {
    $user = investigador();
    $this->actingAs($user);
    $programa = Programa::factory()->create();
    enviosPrevios($user, $programa, 3);
    $borrador = borradorDe($user, $programa);

    $this->post(route('reportes.enviar', $borrador))->assertSessionHas('error');
    expect($borrador->fresh()->estado->value)->toBe('borrador');

    $this->travel(61)->minutes();

    $this->post(route('reportes.enviar', $borrador))->assertSessionMissing('error');
    expect($borrador->fresh()->estado->value)->toBe('enviado');
});

// ---------------------------------------------------------------------------
// "Guardar y enviar" desde el formulario
// ---------------------------------------------------------------------------

test('guardar y enviar bloqueado no crea el informe y devuelve el motivo', function () {
    $user = investigador();
    $this->actingAs($user);
    $programa = Programa::factory()->create();
    enviosPrevios($user, $programa, 3);

    $respuesta = $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Otro hallazgo',
        'descripcion' => 'Detalle del hallazgo',
        'enviar' => true,
    ]);

    $respuesta->assertSessionHasErrors('limite');
    $respuesta->assertSessionHas('error');
    expect(Reporte::query()->where('investigador_id', $user->id)->count())->toBe(3);
});

test('guardar solo un borrador siempre se permite, aunque este en el limite', function () {
    $user = investigador();
    $this->actingAs($user);
    $programa = Programa::factory()->create();
    enviosPrevios($user, $programa, 3);

    $this->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Para enviar mas tarde',
        'descripcion' => 'Detalle del hallazgo',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('reportes', [
        'investigador_id' => $user->id,
        'titulo' => 'Para enviar mas tarde',
        'estado' => 'borrador',
    ]);
});

// ---------------------------------------------------------------------------
// Simulaciones de spam
// ---------------------------------------------------------------------------

test('simulacion: un script envia 15 informes distintos al mismo programa y solo entran 3', function () {
    $atacante = investigador();
    $this->actingAs($atacante);
    $programa = Programa::factory()->create();

    foreach (range(1, 15) as $i) {
        $this->post(route('reportes.store'), [
            'programa_id' => $programa->id,
            'titulo' => "Hallazgo automatico {$i}",
            'descripcion' => "Texto {$i}",
            'enviar' => true,
        ]);
    }

    expect(enviadosDe($atacante))->toBe(3)
        ->and(Reporte::query()->where('investigador_id', $atacante->id)->count())->toBe(3);
});

test('simulacion: un script reparte 18 informes entre 18 programas y solo entran 5 en total', function () {
    $atacante = investigador();
    $this->actingAs($atacante);

    foreach (Programa::factory()->count(18)->create() as $i => $programa) {
        $this->post(route('reportes.store'), [
            'programa_id' => $programa->id,
            'titulo' => "Barrido {$i}",
            'descripcion' => "Texto {$i}",
            'enviar' => true,
        ]);
    }

    expect(enviadosDe($atacante))->toBe(5);
});

test('el bloqueo salta antes que la auditoria: un investigador legitimo nunca es sancionado por rafaga', function () {
    $user = investigador();
    $this->actingAs($user);

    foreach (Programa::factory()->count(12)->create() as $i => $programa) {
        $this->post(route('reportes.store'), [
            'programa_id' => $programa->id,
            'titulo' => "Legitimo {$i}",
            'descripcion' => "Texto {$i}",
            'enviar' => true,
        ]);
    }

    expect(enviadosDe($user))->toBe(5)
        ->and(app(CheatDetectionService::class)->analizarRafaga($user))->toBeNull();
});

test('freno HTTP: la peticion 21 en un minuto recibe 429 y otro usuario no se ve afectado', function () {
    $atacante = investigador();
    $programa = Programa::factory()->create();

    $codigos = [];
    foreach (range(1, 21) as $i) {
        $codigos[] = $this->actingAs($atacante)->post(route('reportes.store'), [
            'programa_id' => $programa->id,
            'titulo' => "Borrador {$i}",
            'descripcion' => "Texto {$i}",
        ])->getStatusCode();
    }

    expect(array_slice($codigos, 0, 20))->each->toBe(302)
        ->and($codigos[20])->toBe(429);

    $this->actingAs(investigador())->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Otro usuario',
        'descripcion' => 'Texto',
    ])->assertRedirect();
});

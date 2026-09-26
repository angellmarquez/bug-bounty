<?php

use App\Enums\EstadoApelacion;
use App\Enums\EstadoSancion;
use App\Enums\GravedadSancion;
use App\Models\Apelacion;
use App\Models\ApelacionEvento;
use App\Models\Programa;
use App\Models\Sancion;
use App\Models\User;
use App\Services\Reputacion\ReputationService;
use App\Services\Reputacion\TrazaApelaciones;
use Illuminate\Support\Facades\DB;

/**
 * Un moderador rechaza un informe y sanciona al investigador (el camino real de una sanción).
 *
 * @return array{0: User, 1: User, 2: Sancion} moderador que sanciona, investigador sancionado, sanción
 */
function sancionadoPorUnModerador(string $gravedad = 'media'): array
{
    $programa = Programa::factory()->create();
    $moderador = moderadorDe($programa);
    $sancionado = investigador();
    $reporte = reporteDe($sancionado, $programa, ['estado' => 'enviado', 'asignado_a' => $moderador->id]);

    test()->actingAs($moderador)->post(route('reportes.rechazar', $reporte), [
        'nota' => 'Evidencia fabricada.',
        'sancionar' => true,
        'gravedad_sancion' => $gravedad,
    ])->assertRedirect();

    return [$moderador, $sancionado, Sancion::where('reporte_id', $reporte->id)->firstOrFail()];
}

function apelar(User $investigador, Sancion $sancion, string $motivo = 'No fabriqué nada.'): Apelacion
{
    test()->actingAs($investigador)->post(route('reputacion.apelar', $sancion), ['motivo' => $motivo])->assertRedirect();

    return Apelacion::where('sancion_id', $sancion->id)->latest('id')->firstOrFail();
}

function resolverApelacion(User $quien, Apelacion $apelacion, bool $aprobada, string $nota = 'Revisado.')
{
    return test()->actingAs($quien)->post(route('apelaciones.resolver', $apelacion), ['aprobada' => $aprobada, 'nota' => $nota]);
}

// ---------------------------------------------------------------------------
// Cadena completa
// ---------------------------------------------------------------------------

test('cadena completa: sancion, apelacion y aprobacion por el administrador devuelven puntos y levantan la suspension', function () {
    [$sancionador, $sancionado, $sancion] = sancionadoPorUnModerador('media');
    $servicio = app(ReputationService::class);

    // La sanción quedó a nombre de quien la aplicó, resta puntos y suspende.
    expect($sancion->aplicada_por)->toBe($sancionador->id)
        ->and($servicio->saldo($sancionado))->toBeLessThan(0);
    $programa = Programa::factory()->create();
    $this->actingAs($sancionado)->post(route('reportes.store'), [
        'programa_id' => $programa->id, 'titulo' => 'Otro', 'descripcion' => 'x',
    ])->assertForbidden();

    $apelacion = apelar($sancionado, $sancion);
    expect($sancion->fresh()->estado)->toBe(EstadoSancion::Apelada);

    $admin = administrador();
    resolverApelacion($admin, $apelacion, true, 'Se acepta la apelación.')
        ->assertRedirect(route('apelaciones.index'))
        ->assertSessionHas('success');

    expect($apelacion->fresh()->estado)->toBe(EstadoApelacion::Aprobada)
        ->and($apelacion->fresh()->resuelta_por)->toBe($admin->id)
        ->and($sancion->fresh()->estado)->toBe(EstadoSancion::Revocada)
        ->and($servicio->saldo($sancionado))->toBe(0)
        ->and($sancionado->fresh()->reputation_score)->toBe(0);

    // Sin sanción vigente vuelve a poder reportar.
    $this->actingAs($sancionado)->post(route('reportes.store'), [
        'programa_id' => $programa->id, 'titulo' => 'Ya puedo reportar', 'descripcion' => 'x',
    ])->assertRedirect()->assertSessionHasNoErrors();
});

test('si la apelacion se rechaza la sancion sigue vigente, la suspension tambien y no se puede apelar de nuevo', function () {
    [, $sancionado, $sancion] = sancionadoPorUnModerador('media');
    $saldoSancionado = app(ReputationService::class)->saldo($sancionado);
    $apelacion = apelar($sancionado, $sancion);

    resolverApelacion(administrador(), $apelacion, false, 'La evidencia es concluyente.')->assertRedirect();

    expect($apelacion->fresh()->estado)->toBe(EstadoApelacion::Rechazada)
        ->and($sancion->fresh()->estado)->toBe(EstadoSancion::Aplicada)
        ->and(app(ReputationService::class)->saldo($sancionado))->toBe($saldoSancionado)
        ->and(app(ReputationService::class)->puedeApelar($sancion->fresh()))->toBeFalse();

    $programa = Programa::factory()->create();
    $this->actingAs($sancionado)->post(route('reportes.store'), [
        'programa_id' => $programa->id, 'titulo' => 'Sigo suspendido', 'descripcion' => 'x',
    ])->assertForbidden();

    // Una sola apelación por sanción: el segundo intento se rechaza con un aviso, no con un error 500.
    $this->actingAs($sancionado)->post(route('reputacion.apelar', $sancion), ['motivo' => 'Insisto.'])
        ->assertSessionHasErrors('motivo');
    expect(Apelacion::where('sancion_id', $sancion->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Quién puede resolver (exclusivo Administrador)
// ---------------------------------------------------------------------------

test('un moderador no puede resolver una apelacion, es competencia exclusiva del administrador', function () {
    [$sancionador, $sancionado, $sancion] = sancionadoPorUnModerador();
    $apelacion = apelar($sancionado, $sancion);

    // Ni el que sancionó ni otro moderador pueden resolverla
    resolverApelacion($sancionador, $apelacion, true)->assertForbidden();
    resolverApelacion(moderador(), $apelacion, true)->assertForbidden();
    expect($apelacion->fresh()->estado)->toBe(EstadoApelacion::Pendiente);

    // Solo el administrador la resuelve
    resolverApelacion(administrador(), $apelacion, true)->assertRedirect();
    expect($apelacion->fresh()->estado)->toBe(EstadoApelacion::Aprobada);
});

test('el administrador puede resolver la apelacion aunque el mismo haya aplicado la sancion y queda anotado', function () {
    $admin = administrador();
    $sancionado = investigador();
    $sancion = app(ReputationService::class)->aplicarSancion($sancionado, 'falso_positivo', GravedadSancion::Leve, aplicadaPor: $admin);
    $apelacion = apelar($sancionado, $sancion);

    resolverApelacion($admin, $apelacion, false, 'Se mantiene.')->assertRedirect();

    $paso = ApelacionEvento::where('apelacion_id', $apelacion->id)->where('tipo', TrazaApelaciones::RECHAZADA)->firstOrFail();
    expect($apelacion->fresh()->estado)->toBe(EstadoApelacion::Rechazada)
        ->and($paso->actor_rol)->toBe('administrador')
        ->and($paso->datos['mismo_que_sanciono'])->toBeTrue();
});

test('nadie resuelve la apelacion que el mismo presento', function () {
    // Un moderador que también es investigador y fue sancionado por otro moderador.
    [, , $sancionOriginal] = sancionadoPorUnModerador();
    $doble = conRol(User::factory()->create(), ['investigador', 'moderador']);
    $sancion = app(ReputationService::class)->aplicarSancion($doble, 'falso_positivo', GravedadSancion::Leve, aplicadaPor: moderador());
    $apelacion = apelar($doble, $sancion);

    resolverApelacion($doble, $apelacion, true)->assertForbidden();
    expect($apelacion->fresh()->estado)->toBe(EstadoApelacion::Pendiente);
});

test('un investigador comun o un invitado no resuelven apelaciones', function () {
    [, $sancionado, $sancion] = sancionadoPorUnModerador();
    $apelacion = apelar($sancionado, $sancion);

    resolverApelacion(investigador(), $apelacion, true)->assertForbidden();
    resolverApelacion(propietarioDeEmpresa(), $apelacion, true)->assertForbidden();
    auth()->logout();
    $this->post(route('apelaciones.resolver', $apelacion), ['aprobada' => true, 'nota' => 'x'])->assertRedirect(route('login'));
    expect($apelacion->fresh()->estado)->toBe(EstadoApelacion::Pendiente);
});

test('la nota de resolucion es obligatoria', function () {
    [, $sancionado, $sancion] = sancionadoPorUnModerador();
    $apelacion = apelar($sancionado, $sancion);

    resolverApelacion(administrador(), $apelacion, true, '')->assertSessionHasErrors('nota');
    expect($apelacion->fresh()->estado)->toBe(EstadoApelacion::Pendiente);
});

// ---------------------------------------------------------------------------
// Plazo
// ---------------------------------------------------------------------------

test('se puede apelar hasta el ultimo minuto del plazo y ni un minuto despues', function () {
    $sancionado = investigador();
    $sancion = app(ReputationService::class)->aplicarSancion($sancionado, 'falso_positivo', GravedadSancion::Leve);
    $plazo = $sancion->plazo_apelacion;

    $this->travelTo($plazo->copy()->subMinute());
    expect(app(ReputationService::class)->puedeApelar($sancion->fresh()))->toBeTrue();

    $this->travelTo($plazo->copy()->addMinute());
    expect(app(ReputationService::class)->puedeApelar($sancion->fresh()))->toBeFalse();
    // Pasado el plazo, ABAC ni siquiera deja llegar a la petición.
    $this->actingAs($sancionado)->post(route('reputacion.apelar', $sancion), ['motivo' => 'Tarde.'])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Huella: control de quién hizo qué
// ---------------------------------------------------------------------------

test('cada paso queda registrado con quien lo hizo, su rol, la ip y una huella encadenada', function () {
    [$sancionador, $sancionado, $sancion] = sancionadoPorUnModerador();
    $apelacion = apelar($sancionado, $sancion, 'Mi motivo.');
    $resolutor = administrador(['name' => 'Administradora Revisora']);
    resolverApelacion($resolutor, $apelacion, true, 'Aceptada.');

    $pasos = ApelacionEvento::where('apelacion_id', $apelacion->id)->orderBy('id')->get();
    expect($pasos)->toHaveCount(2);

    [$presentada, $resuelta] = [$pasos[0], $pasos[1]];
    expect($presentada->tipo)->toBe('presentada')
        ->and($presentada->actor_id)->toBe($sancionado->id)
        ->and($presentada->actor_rol)->toBe('investigador')
        ->and($presentada->nota)->toBe('Mi motivo.')
        ->and($presentada->ip)->not->toBeNull()
        ->and($presentada->datos['sancion_aplicada_por'])->toBe($sancionador->id)
        ->and($presentada->huella_anterior)->toBeNull()
        ->and($presentada->huella)->toHaveLength(64)
        ->and($resuelta->tipo)->toBe('aprobada')
        ->and($resuelta->actor_id)->toBe($resolutor->id)
        ->and($resuelta->actor_nombre)->toBe('Administradora Revisora')
        ->and($resuelta->actor_rol)->toBe('administrador')
        ->and($resuelta->huella_anterior)->toBe($presentada->huella)
        ->and(app(TrazaApelaciones::class)->verificar($apelacion))->toBeTrue();
});

test('si alguien altera o borra un paso registrado la cadena de huellas lo detecta', function () {
    [, $sancionado, $sancion] = sancionadoPorUnModerador();
    $apelacion = apelar($sancionado, $sancion);
    resolverApelacion(administrador(), $apelacion, false, 'Nota original.');
    $traza = app(TrazaApelaciones::class);
    expect($traza->verificar($apelacion))->toBeTrue();

    // 1) Cambiar el texto de la decision.
    DB::table('apelacion_eventos')->where('apelacion_id', $apelacion->id)->where('tipo', 'rechazada')->update(['nota' => 'Nota falsificada.']);
    expect($traza->verificar($apelacion))->toBeFalse();
    DB::table('apelacion_eventos')->where('apelacion_id', $apelacion->id)->where('tipo', 'rechazada')->update(['nota' => 'Nota original.']);
    expect($traza->verificar($apelacion))->toBeTrue();

    // 2) Cambiar quién la tomó.
    DB::table('apelacion_eventos')->where('apelacion_id', $apelacion->id)->where('tipo', 'rechazada')->update(['actor_id' => $sancionado->id]);
    expect($traza->verificar($apelacion))->toBeFalse();

    // 3) Borrar el primer paso.
    DB::table('apelacion_eventos')->where('apelacion_id', $apelacion->id)->where('tipo', 'presentada')->delete();
    expect($traza->verificar($apelacion))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Paginas: seguimiento del investigador y panel de resolucion
// ---------------------------------------------------------------------------

test('el investigador ve el seguimiento de su apelacion con el rol de quien decidio, sin nombres ni ip', function () {
    [, $sancionado, $sancion] = sancionadoPorUnModerador();
    $apelacion = apelar($sancionado, $sancion);
    resolverApelacion(administrador(['name' => 'Nombre Secreto']), $apelacion, false, 'Se mantiene la sancion.');

    $respuesta = $this->actingAs($sancionado)->get(route('reputacion.apelacion', $apelacion));

    $respuesta->assertOk()->assertInertia(fn ($page) => $page
        ->component('reputacion/Apelacion')
        ->where('apelacion.estado', 'rechazada')
        ->where('apelacion.nota_resolucion', 'Se mantiene la sancion.')
        ->where('apelacion.sancion.aplicada_por_rol', 'moderador')
        ->where('apelacion.cadena_valida', true)
        ->has('apelacion.eventos', 2)
        ->where('apelacion.eventos.1.actor_rol', 'administrador'));

    $texto = json_encode($respuesta->inertiaProps());
    expect($texto)->not->toContain('Nombre Secreto')->not->toContain('"ip"');
});

test('un investigador no puede ver el seguimiento de la apelacion de otro', function () {
    [, $sancionado, $sancion] = sancionadoPorUnModerador();
    $apelacion = apelar($sancionado, $sancion);

    $this->actingAs(investigador())->get(route('reputacion.apelacion', $apelacion))->assertForbidden();
});

test('el panel de resolucion muestra quien sanciono, quien decidio, la ip y si la cadena es valida', function () {
    [$sancionador, $sancionado, $sancion] = sancionadoPorUnModerador();
    $apelacion = apelar($sancionado, $sancion);
    $resolutor = administrador(['name' => 'Administrador Decisor']);
    resolverApelacion($resolutor, $apelacion, true, 'Aceptada.');

    $this->actingAs(administrador())->get(route('apelaciones.show', $apelacion))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->component('moderacion/apelaciones/Show')
        ->where('apelacion.sancion.aplicada_por.id', $sancionador->id)
        ->where('apelacion.resuelta_por.name', 'Administrador Decisor')
        ->where('apelacion.cadena_valida', true)
        ->where('apelacion.eventos.0.actor.id', $sancionado->id)
        ->where('apelacion.eventos.1.actor_rol', 'administrador')
        ->has('apelacion.eventos.1.ip')
        ->has('apelacion.eventos.1.huella'));
});

test('el listado pone las pendientes primero para el administrador', function () {
    [, $sancionado, $sancion] = sancionadoPorUnModerador();
    $pendiente = apelar($sancionado, $sancion);

    // Otra apelación ya resuelta, más reciente: aun así va después de la pendiente.
    $otro = investigador();
    $otraSancion = app(ReputationService::class)->aplicarSancion($otro, 'rafaga_reportes', GravedadSancion::Leve);
    $resuelta = apelar($otro, $otraSancion);
    resolverApelacion(administrador(), $resuelta, true);

    $this->actingAs(administrador())->get(route('apelaciones.index'))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->component('moderacion/apelaciones/Index')
        ->where('apelaciones.data.0.id', $pendiente->id)
        ->where('apelaciones.data.0.puede_resolver', true)
        ->where('apelaciones.data.1.id', $resuelta->id));

    $this->actingAs(administrador())->get(route('apelaciones.index', ['estado' => 'aprobada']))
        ->assertInertia(fn ($page) => $page->has('apelaciones.data', 1)->where('apelaciones.data.0.id', $resuelta->id));
});

test('solo administradores entran al panel de resolucion de apelaciones', function () {
    $this->actingAs(investigador())->get(route('apelaciones.index'))->assertForbidden();
    $this->actingAs(propietarioDeEmpresa())->get(route('apelaciones.index'))->assertForbidden();
    $this->actingAs(moderador())->get(route('apelaciones.index'))->assertForbidden();
    $this->actingAs(administrador())->get(route('apelaciones.index'))->assertOk();
    $this->actingAs(administrador())->get('/admin/apelaciones')->assertRedirect('/moderacion/apelaciones');
});

test('la pagina de sanciones solo ofrece apelar mientras se pueda', function () {
    [, $sancionado, $sancion] = sancionadoPorUnModerador();
    $this->actingAs($sancionado)->get(route('reputacion.sanciones'))
        ->assertInertia(fn ($page) => $page->where('sanciones.data.0.puede_apelar', true));

    $apelacion = apelar($sancionado, $sancion);
    resolverApelacion(administrador(), $apelacion, false);

    $this->actingAs($sancionado)->get(route('reputacion.sanciones'))
        ->assertInertia(fn ($page) => $page->where('sanciones.data.0.puede_apelar', false));
});

test('el saldo del historial coincide con la reputacion mostrada tras aprobar y tras rechazar', function () {
    $servicio = app(ReputationService::class);

    [, $a, $sancionA] = sancionadoPorUnModerador('grave');
    resolverApelacion(administrador(), apelar($a, $sancionA), true);
    expect($servicio->saldo($a))->toBe($a->fresh()->reputation_score)->toBe(0);

    [, $b, $sancionB] = sancionadoPorUnModerador('grave');
    resolverApelacion(administrador(), apelar($b, $sancionB), false);
    expect($servicio->saldo($b))->toBe($b->fresh()->reputation_score)->toBeLessThan(0);
});

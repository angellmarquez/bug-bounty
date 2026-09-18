<?php

use App\Abac\AbacEngine;
use App\Abac\AbacException;
use App\Abac\DecisionAbac;

/**
 * Restaura la configuración ABAC original tras cada test para no contaminar
 * los demás archivos (Laravel conserva la config entre tests del proceso).
 */
afterEach(function () {
    config(['abac' => require base_path('config/abac.php')]);
});

/**
 * Helper: sobreescribe las reglas ABAC para el test.
 *
 * @param  array<int, array<string, mixed>>  $reglas
 */
function flotaMotor(array $reglas): void
{
    config(['abac.reglas' => $reglas]);
    config(['abac.deny_by_default' => true]);
}

/**
 * Construye una regla mínima para los tests del motor.
 *
 * @param  array<int, string>  $acciones
 * @param  array<string, mixed>  $sujeto
 * @param  array<string, mixed>  $objeto
 * @param  array<string, mixed>  $entorno
 * @return array<string, mixed>
 */
function reglaMotor(
    string $id,
    array $acciones = ['*'],
    array $sujeto = [],
    array $objeto = [],
    array $entorno = [],
    string $decision = 'permitir',
    int $prioridad = 50,
): array {
    return [
        'id' => $id,
        'prioridad' => $prioridad,
        'acciones' => $acciones,
        'sujeto' => $sujeto,
        'objeto' => $objeto,
        'entorno' => $entorno,
        'decision' => $decision,
    ];
}

test('sin reglas aplicables se deniega por defecto (fail-closed)', function () {
    flotaMotor([]);

    $decision = app(AbacEngine::class)->evaluar('reportes.ver');

    expect($decision->estaPermitida())->toBeFalse()
        ->and($decision->estaDenegada())->toBeTrue()
        ->and($decision->decision)->toBe(DecisionAbac::Denegar)
        ->and($decision->regla)->toBeNull()
        ->and($decision->motivo())->toContain('fail-closed');
});

test('una regla que coincide permite con su id como motivo', function () {
    flotaMotor([
        reglaMotor('permite-todo', objeto: [], entorno: []),
    ]);

    $decision = app(AbacEngine::class)->evaluar('cualquier.accion');

    expect($decision->estaPermitida())->toBeTrue()
        ->and($decision->regla)->toBe('permite-todo');
});

test('el deny gana aunque también coincida una regla de permitir', function () {
    flotaMotor([
        reglaMotor('deniega', decision: 'denegar', prioridad: 1),
        reglaMotor('permite', prioridad: 99),
    ]);

    $decision = app(AbacEngine::class)->evaluar('reportes.ver');

    expect($decision->estaDenegada())->toBeTrue()
        ->and($decision->regla)->toBe('deniega');
});

test('la regla deny que anticipa cualquier allow se registra en la traza', function () {
    flotaMotor([
        reglaMotor('deniega', decision: 'denegar', prioridad: 1),
        reglaMotor('permite', prioridad: 99),
    ]);

    $decision = app(AbacEngine::class)->evaluar('reportes.ver');

    expect(collect($decision->detalle)->pluck('regla')->all())->toContain('deniega', 'permite')
        ->and($decision->detalle[0]['coincide'])->toBeTrue();
});

test('con deny_by_default desactivado la decisión es no_aplicable', function () {
    flotaMotor([]);
    config(['abac.deny_by_default' => false]);

    $decision = app(AbacEngine::class)->evaluar('reportes.ver');

    expect($decision->estaPermitida())->toBeFalse()
        ->and($decision->decision)->toBe(DecisionAbac::NoAplicable);
});

test('el comodín de prefijo reportes.* solo casa acciones de reportes', function () {
    flotaMotor([
        reglaMotor('comodin', acciones: ['reportes.*']),
    ]);

    $motor = app(AbacEngine::class);

    expect($motor->evaluar('reportes.ver')->estaPermitida())->toBeTrue()
        ->and($motor->evaluar('reportes.cerrar')->estaPermitida())->toBeTrue()
        ->and($motor->evaluar('programas.ver')->estaPermitida())->toBeFalse();
});

test('el comodín total * casa cualquier acción', function () {
    flotaMotor([
        reglaMotor('total'),
    ]);

    $motor = app(AbacEngine::class);

    expect($motor->evaluar('reportes.ver')->estaPermitida())->toBeTrue()
        ->and($motor->evaluar('programas.cambiar_estado')->estaPermitida())->toBeTrue();
});

test('la condición de sujeto sobre roles usa el slug asignado', function () {
    flotaMotor([
        reglaMotor('investigador', sujeto: ['roles' => ['contains' => 'investigador']]),
    ]);

    $motor = app(AbacEngine::class);
    $investigador = investigador();

    expect($motor->evaluar('reportes.ver', null, $investigador)->estaPermitida())->toBeTrue()
        ->and($motor->evaluar('reportes.ver')->estaPermitida())->toBeFalse();
});

test('la referencia @sujeto.id compara el objeto contra el usuario', function () {
    flotaMotor([
        reglaMotor('autor', objeto: ['investigador_id' => ['=' => '@sujeto.id']]),
    ]);

    $autor = investigador();
    $otro = investigador();
    $reporte = reporteDe($autor);
    $motor = app(AbacEngine::class);

    expect($motor->evaluar('reportes.ver', $reporte, $autor)->estaPermitida())->toBeTrue()
        ->and($motor->evaluar('reportes.ver', $reporte, $otro)->estaPermitida())->toBeFalse();
});

test('is_null e is_not_null distinguen el atributo ausente', function () {
    flotaMotor([
        reglaMotor('anulado', objeto: ['asignado_a' => ['is_null']]),
    ]);

    $reporte = reporteDe(investigador(), atributos: ['asignado_a' => null]);
    $asignadoA = investigador();
    $asignado = reporteDe(investigador(), atributos: ['asignado_a' => $asignadoA->id]);

    $motor = app(AbacEngine::class);

    expect($motor->evaluar('reportes.ver', $reporte)->estaPermitida())->toBeTrue()
        ->and($motor->evaluar('reportes.ver', $asignado)->estaPermitida())->toBeFalse();
});

test('un atributo ausente no coincide con un operador distinto de is_null', function () {
    flotaMotor([
        reglaMotor('ajeno', objeto: ['ajeno_id' => ['=' => '@sujeto.id']]),
    ]);

    $usuario = investigador();
    $objeto = ['id' => 42];

    expect(app(AbacEngine::class)->evaluar('y.ver', $objeto, $usuario)->estaPermitida())->toBeFalse();
});

test('las condiciones del entorno soportan comparaciones de fecha', function () {
    flotaMotor([
        reglaMotor('reciente', objeto: ['creada_en' => ['<=' => '@entorno.ahora']]),
    ]);

    expect(app(AbacEngine::class)->evaluar('x.ver', ['creada_en' => '2020-01-01 00:00:00'])->estaPermitida())->toBeTrue()
        ->and(app(AbacEngine::class)->evaluar('x.ver', ['creada_en' => '2030-01-01 00:00:00'])->estaPermitida())->toBeFalse();
});

test('los operadores in y not_in evalúan sobre listas', function () {
    flotaMotor([
        reglaMotor('lista', objeto: ['estado' => ['in' => ['abierto', 'pendiente']]]),
    ]);

    $motor = app(AbacEngine::class);

    expect($motor->evaluar('x.ver', ['estado' => 'abierto'])->estaPermitida())->toBeTrue()
        ->and($motor->evaluar('x.ver', ['estado' => 'cerrado'])->estaPermitida())->toBeFalse();

    flotaMotor([
        reglaMotor('no-lista', objeto: ['estado' => ['not_in' => ['cerrado', 'archivado']]]),
    ]);

    expect($motor->evaluar('x.ver', ['estado' => 'abierto'])->estaPermitida())->toBeTrue()
        ->and($motor->evaluar('x.ver', ['estado' => 'cerrado'])->estaPermitida())->toBeFalse();
});

test('un operador desconocido lanza AbacException', function () {
    flotaMotor([
        reglaMotor('mala', objeto: ['x' => ['like' => 'a']]),
    ]);

    app(AbacEngine::class)->evaluar('cualquier.accion');
})->throws(AbacException::class);

test('validarReglas acepta la configuración por defecto', function () {
    expect(app(AbacEngine::class)->validarReglas())->toBeNull();
});

test('validarReglas rechaza reglas malformadas', function (array $reglas) {
    app(AbacEngine::class)->validarReglas($reglas);
})->with([
    'sin id' => [[['acciones' => ['*']]]],
    'sin acciones' => [[['id' => 'r', 'acciones' => []]]],
    'decisión desconocida' => [[['id' => 'r', 'acciones' => ['*'], 'decision' => 'quizas']]],
    'operador no soportado' => [[['id' => 'r', 'acciones' => ['*'], 'sujeto' => ['roles' => ['like' => 'x']]]]],
    'condición sin operadores' => [[['id' => 'r', 'acciones' => ['*'], 'objeto' => ['estado' => 'activo']]]],
])->throws(AbacException::class);

<?php

use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Bounties\BountyBlockchainService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const WALLET_INVESTIGADOR = '0x71c83638372331e200c025434d31b057c9a63974';
const WALLET_EMPRESA = '0x1111111111111111111111111111111111111111';
const USDC_AMOY = '0x41E94Eb019C0762f9Bfcf9Fb1E58725BfB0e7582';
const TOPIC_TRANSFER = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

/**
 * Empresa con un programa de recompensas y un informe validado de un investigador con wallet.
 *
 * @return array{empresa: User, programa: Programa, investigador: User, reporte: Reporte}
 */
function escenarioBounty(array $reporte = [], array $programa = []): array
{
    $empresa = propietarioDeEmpresa();
    $prog = programaDeEmpresa($empresa, ['tiene_recompensas' => true, ...$programa]);
    $investigador = investigador(['wallet_address' => WALLET_INVESTIGADOR]);

    return [
        'empresa' => $empresa,
        'programa' => $prog,
        'investigador' => $investigador,
        'reporte' => reporteDe($investigador, $prog, ['estado' => 'validado', ...$reporte]),
    ];
}

function hashTx(string $caracter): string
{
    return '0x'.str_repeat($caracter, 64);
}

function palabra32(string $hex): string
{
    return '0x'.str_pad(ltrim(preg_replace('/^0x/', '', strtolower($hex)), '0'), 64, '0', STR_PAD_LEFT);
}

/** Log de un Transfer ERC-20 como lo devuelve eth_getTransactionReceipt. */
function logTransfer(string $destino, string $unidades, string $token = USDC_AMOY, string $origen = WALLET_EMPRESA): array
{
    return [
        'address' => strtolower($token),
        'topics' => [TOPIC_TRANSFER, palabra32($origen), palabra32($destino)],
        'data' => palabra32(dechex((int) $unidades)),
    ];
}

/**
 * Finge el nodo JSON-RPC de la red: devuelve `$recibo` y `$ultimoBloque`.
 *
 * @param  array<string, mixed>|null  $recibo
 */
function rpcFalso(?array $recibo, int $ultimoBloque = 1000): void
{
    // Un cliente nuevo en cada llamada: si no, el fake anterior seguiría respondiendo primero.
    Http::swap(new Factory(app('events')));
    Http::fake(fn (Request $request) => Http::response([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => match ($request['method']) {
            'eth_getTransactionReceipt' => $recibo,
            'eth_blockNumber' => '0x'.dechex($ultimoBloque),
            default => null,
        },
    ]));
}

function reciboValido(array $logs, int $bloque = 998, string $status = '0x1'): array
{
    return ['status' => $status, 'blockNumber' => '0x'.dechex($bloque), 'from' => WALLET_EMPRESA, 'logs' => $logs];
}

// ---------------------------------------------------------------------
// Asignar el monto
// ---------------------------------------------------------------------

test('la empresa asigna la recompensa en USDC y el investigador recibe el aviso', function () {
    ['empresa' => $empresa, 'reporte' => $reporte, 'investigador' => $investigador] = escenarioBounty();

    $this->actingAs($empresa)->post(route('reportes.bounty.asignar', $reporte), ['monto' => 250.5])->assertRedirect()->assertSessionHasNoErrors();

    expect($reporte->fresh())
        ->bounty_estado->toBe('asignado')
        ->bounty_moneda->toBe('USDC')
        ->bounty_red->toBe('polygon_amoy')
        ->and((float) $reporte->fresh()->bounty_monto)->toBe(250.5)
        ->and($investigador->notifications()->get()->pluck('data.titulo'))->toContain('Tu informe tiene una recompensa asignada');
});

test('solo la empresa duena asigna: ni el investigador ni el moderador', function () {
    ['reporte' => $reporte, 'investigador' => $investigador] = escenarioBounty();

    $this->actingAs($investigador)->post(route('reportes.bounty.asignar', $reporte), ['monto' => 100])->assertForbidden();
    $this->actingAs(moderadorDe($reporte))->post(route('reportes.bounty.asignar', $reporte), ['monto' => 100])->assertForbidden();
    $this->actingAs(propietarioDeEmpresa())->post(route('reportes.bounty.asignar', $reporte), ['monto' => 100])->assertForbidden();
});

test('el monto respeta el rango del programa', function () {
    ['empresa' => $empresa, 'reporte' => $reporte] = escenarioBounty([], ['recompensa_min' => 50, 'recompensa_max' => 1000]);
    $this->actingAs($empresa);

    $this->post(route('reportes.bounty.asignar', $reporte), ['monto' => 10])->assertSessionHasErrors('monto');
    $this->post(route('reportes.bounty.asignar', $reporte), ['monto' => 1500])->assertSessionHasErrors('monto');
    $this->post(route('reportes.bounty.asignar', $reporte), ['monto' => 700])->assertSessionHasNoErrors();
});

test('las unidades de USDC se calculan con 6 decimales', function () {
    $servicio = app(BountyBlockchainService::class);

    expect($servicio->unidades(12.34))->toBe('12340000')
        ->and($servicio->unidades(0.01))->toBe('10000')
        ->and($servicio->unidades(1000000))->toBe('1000000000000');
});

// ---------------------------------------------------------------------
// Pagar y verificar en la blockchain
// ---------------------------------------------------------------------

test('un pago correcto con las confirmaciones exigidas queda pagado y verificado', function () {
    ['empresa' => $empresa, 'reporte' => $reporte, 'investigador' => $investigador] = escenarioBounty(['bounty_monto' => 250, 'bounty_estado' => 'asignado']);
    rpcFalso(reciboValido([logTransfer(WALLET_INVESTIGADOR, '250000000')], bloque: 998), ultimoBloque: 1000);

    $this->actingAs($empresa)->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('a'), 'pagador' => WALLET_EMPRESA])
        ->assertRedirect()->assertSessionHasNoErrors();

    $pagado = $reporte->fresh();
    expect($pagado)
        ->bounty_estado->toBe('pagado')
        ->bounty_tx_hash->toBe(hashTx('a'))
        ->bounty_bloque->toBe(998)
        ->bounty_wallet_destino->toBe(WALLET_INVESTIGADOR)
        ->bounty_pagador->toBe(WALLET_EMPRESA)
        ->bounty_error->toBeNull()
        ->and($pagado->bounty_pagado_en)->not->toBeNull()
        ->and($pagado->eventos()->where('tipo', 'bounty')->count())->toBe(2)
        ->and(Auditoria::query()->where('accion', 'reportes.bounty_pagado')->exists())->toBeTrue()
        ->and($investigador->notifications()->get()->pluck('data.titulo'))->toContain('Recibiste el pago de tu recompensa');
});

test('sin suficientes confirmaciones queda en verificacion y se confirma al volver a comprobar', function () {
    ['empresa' => $empresa, 'reporte' => $reporte] = escenarioBounty(['bounty_monto' => 100, 'bounty_estado' => 'asignado']);
    $recibo = reciboValido([logTransfer(WALLET_INVESTIGADOR, '100000000')], bloque: 1000);
    rpcFalso($recibo, ultimoBloque: 1000);

    $this->actingAs($empresa)->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('b')]);
    expect($reporte->fresh())->bounty_estado->toBe('verificando')->bounty_error->toContain('1 de 3');

    rpcFalso($recibo, ultimoBloque: 1002);
    $this->post(route('reportes.bounty.comprobar', $reporte))->assertRedirect();

    expect($reporte->fresh()->bounty_estado)->toBe('pagado');
});

test('una transaccion que no aparece se da por fallida al expirar el plazo', function () {
    ['empresa' => $empresa, 'reporte' => $reporte] = escenarioBounty(['bounty_monto' => 100, 'bounty_estado' => 'asignado']);
    rpcFalso(null);

    $this->actingAs($empresa)->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('c')]);
    expect($reporte->fresh()->bounty_estado)->toBe('verificando');

    $this->travel(31)->minutes();
    $this->post(route('reportes.bounty.comprobar', $reporte));

    expect($reporte->fresh())->bounty_estado->toBe('fallido')->bounty_error->toContain('no se confirmó');
});

test('se rechaza el pago que no cumple: destino, monto, token o estado', function (array $recibo, string $motivo) {
    ['empresa' => $empresa, 'reporte' => $reporte] = escenarioBounty(['bounty_monto' => 100, 'bounty_estado' => 'asignado']);
    rpcFalso($recibo);

    $this->actingAs($empresa)->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('d')]);

    expect($reporte->fresh())->bounty_estado->toBe('fallido')->bounty_error->toContain($motivo)
        ->and($reporte->fresh()->bounty_pagado_en)->toBeNull();
})->with([
    'a otra wallet' => [fn () => reciboValido([logTransfer('0x2222222222222222222222222222222222222222', '100000000')]), 'no transfiere USDC'],
    'monto menor' => [fn () => reciboValido([logTransfer(WALLET_INVESTIGADOR, '99990000')]), 'Monto insuficiente'],
    'token falso' => [fn () => reciboValido([logTransfer(WALLET_INVESTIGADOR, '100000000', token: '0x3333333333333333333333333333333333333333')]), 'no transfiere USDC'],
    'transaccion revertida' => [fn () => reciboValido([logTransfer(WALLET_INVESTIGADOR, '100000000')], status: '0x0'), 'revertida'],
    'sin transferencias' => [fn () => reciboValido([]), 'no transfiere USDC'],
]);

test('si el proveedor RPC falla, el pago nunca se da por bueno', function (int $status) {
    ['empresa' => $empresa, 'reporte' => $reporte] = escenarioBounty(['bounty_monto' => 100, 'bounty_estado' => 'asignado']);
    Http::fake(['*' => Http::response('caido', $status)]);

    $this->actingAs($empresa)->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('e')]);

    expect($reporte->fresh())->bounty_estado->toBe('verificando')->bounty_error->toContain('No se pudo consultar');
})->with([500, 429]);

test('tras un pago fallido se puede registrar otra transaccion', function () {
    ['empresa' => $empresa, 'reporte' => $reporte] = escenarioBounty(['bounty_monto' => 100, 'bounty_estado' => 'asignado']);
    $this->actingAs($empresa);

    rpcFalso(reciboValido([logTransfer(WALLET_INVESTIGADOR, '50000000')]));
    $this->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('1')]);
    expect($reporte->fresh()->bounty_estado)->toBe('fallido');

    rpcFalso(reciboValido([logTransfer(WALLET_INVESTIGADOR, '100000000')]));
    $this->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('2')])->assertSessionHasNoErrors();

    expect($reporte->fresh())->bounty_estado->toBe('pagado')->bounty_tx_hash->toBe(hashTx('2'));
});

test('un bounty pagado ya no se puede sobrescribir ni cambiar de monto', function () {
    ['empresa' => $empresa, 'reporte' => $reporte] = escenarioBounty(['bounty_monto' => 100, 'bounty_estado' => 'asignado']);
    $this->actingAs($empresa);
    rpcFalso(reciboValido([logTransfer(WALLET_INVESTIGADOR, '100000000')]));
    $this->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('f')]);

    $this->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('9')])->assertSessionHasErrors('tx_hash');
    $this->post(route('reportes.bounty.asignar', $reporte), ['monto' => 5000])->assertSessionHasErrors('monto');

    expect($reporte->fresh())->bounty_estado->toBe('pagado')->bounty_tx_hash->toBe(hashTx('f'))
        ->and((float) $reporte->fresh()->bounty_monto)->toBe(100.0);
});

test('una misma transaccion no puede pagar dos informes', function () {
    ['empresa' => $empresa, 'programa' => $programa, 'reporte' => $reporte] = escenarioBounty(['bounty_monto' => 100, 'bounty_estado' => 'asignado']);
    $otro = reporteDe(investigador(['wallet_address' => WALLET_INVESTIGADOR]), $programa, ['estado' => 'validado', 'bounty_monto' => 100, 'bounty_estado' => 'asignado']);
    rpcFalso(reciboValido([logTransfer(WALLET_INVESTIGADOR, '100000000')]));
    $this->actingAs($empresa);

    $this->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('7')])->assertSessionHasNoErrors();
    $this->post(route('reportes.bounty.transaccion', $otro), ['tx_hash' => strtoupper(hashTx('7'))])->assertSessionHasErrors('tx_hash');
});

test('se verifica contra la wallet fijada al registrar, aunque el investigador la cambie despues', function () {
    ['empresa' => $empresa, 'reporte' => $reporte, 'investigador' => $investigador] = escenarioBounty(['bounty_monto' => 100, 'bounty_estado' => 'asignado']);
    rpcFalso(null);
    $this->actingAs($empresa)->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('8')]);

    $investigador->update(['wallet_address' => '0x4444444444444444444444444444444444444444']);
    rpcFalso(reciboValido([logTransfer(WALLET_INVESTIGADOR, '100000000')]));
    $this->post(route('reportes.bounty.comprobar', $reporte));

    expect($reporte->fresh())->bounty_estado->toBe('pagado')->bounty_wallet_destino->toBe(WALLET_INVESTIGADOR);
});

test('no se puede pagar si el investigador no tiene wallet', function () {
    ['empresa' => $empresa, 'reporte' => $reporte, 'investigador' => $investigador] = escenarioBounty(['bounty_monto' => 100, 'bounty_estado' => 'asignado']);
    $investigador->update(['wallet_address' => null]);

    $this->actingAs($empresa)->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('6')])->assertSessionHasErrors('tx_hash');

    expect($reporte->fresh()->bounty_estado)->toBe('asignado');
});

test('el comando programado confirma los pagos pendientes', function () {
    ['empresa' => $empresa, 'reporte' => $reporte] = escenarioBounty(['bounty_monto' => 100, 'bounty_estado' => 'asignado']);
    $recibo = reciboValido([logTransfer(WALLET_INVESTIGADOR, '100000000')], bloque: 1000);
    rpcFalso($recibo, ultimoBloque: 1000);
    $this->actingAs($empresa)->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashTx('5')]);

    rpcFalso($recibo, ultimoBloque: 1010);
    $this->artisan('bounties:verificar-pendientes')->assertSuccessful();

    expect($reporte->fresh()->bounty_estado)->toBe('pagado');
});

test('mainnet queda bloqueada salvo que se habilite a proposito', function () {
    config(['bounty.red' => 'polygon', 'bounty.permitir_mainnet' => false]);
    ['empresa' => $empresa, 'reporte' => $reporte] = escenarioBounty();

    expect(fn () => app(BountyBlockchainService::class)->red())->toThrow(RuntimeException::class);

    config(['bounty.permitir_mainnet' => true]);
    expect(app(BountyBlockchainService::class)->red()['chain_id'])->toBe(137);
});

// ---------------------------------------------------------------------
// Privacidad: la wallet identifica al investigador en la blockchain
// ---------------------------------------------------------------------

test('la wallet solo la ven quien paga y el investigador, nunca el moderador en triaje ciego', function () {
    ['empresa' => $empresa, 'reporte' => $reporte, 'investigador' => $investigador] = escenarioBounty(['bounty_monto' => 100, 'bounty_estado' => 'asignado']);
    $enRevision = reporteDe($investigador, $reporte->programa, ['estado' => 'en_revision']);

    $this->actingAs(moderadorDe($enRevision))->get(route('reportes.show', $enRevision))
        ->assertInertia(fn (Assert $p) => $p->where('reporte.investigador_id', 0)->where('reporte.bounty.wallet_destino', null));

    $this->actingAs($empresa)->get(route('reportes.show', $reporte))
        ->assertInertia(fn (Assert $p) => $p
            ->where('reporte.bounty.wallet_destino', WALLET_INVESTIGADOR)
            ->where('reporte.bounty.monto_unidades', '100000000')
            ->where('reporte.bounty.red.chain_id', 80002)
            ->where('reporte.bounty.red.propina_minima_gwei', 30)
            ->where('accionesDisponibles.pagar_bounty', true));

    $this->actingAs($investigador)->get(route('reportes.show', $reporte))
        ->assertInertia(fn (Assert $p) => $p->where('reporte.bounty.wallet_destino', WALLET_INVESTIGADOR)->where('accionesDisponibles.pagar_bounty', false));
});

// ---------------------------------------------------------------------
// Plan de la empresa
// ---------------------------------------------------------------------

test('el administrador activa y quita el Plan Profesional; la empresa no puede hacerlo', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $duena = miembroDeEmpresa($empresa);

    $this->actingAs($duena)->post(route('admin.empresas.plan', $empresa), ['plan' => 'profesional'])->assertForbidden();

    $this->actingAs(administrador())->post(route('admin.empresas.plan', $empresa), ['plan' => 'profesional'])->assertRedirect();
    expect($empresa->fresh()->esPlanProfesional())->toBeTrue()
        ->and($duena->notifications()->get()->pluck('data.titulo'))->toContain('Tu empresa tiene el Plan Profesional');

    $this->post(route('admin.empresas.plan', $empresa), ['plan' => 'comunitario'])->assertRedirect();
    expect($empresa->fresh()->plan)->toBe('comunitario');
});

test('sin Plan Profesional la empresa sigue editando su programa privado, pero no vuelve privado uno publico', function () {
    $duena = propietarioDeEmpresa();
    $privado = conObjetivo(programaDeEmpresa($duena, ['es_publico' => false]));
    $publico = conObjetivo(programaDeEmpresa($duena, ['es_publico' => true]));
    $this->actingAs($duena);

    $this->put(route('programas.update', $privado), ['nombre' => 'Nuevo nombre', 'descripcion' => $privado->descripcion, 'es_publico' => '0'])
        ->assertSessionHasNoErrors();
    $this->put(route('programas.update', $publico), ['nombre' => $publico->nombre, 'descripcion' => $publico->descripcion, 'es_publico' => '0'])
        ->assertSessionHasErrors('es_publico');

    expect($privado->fresh()->nombre)->toBe('Nuevo nombre');
});

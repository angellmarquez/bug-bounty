<?php

use App\Enums\GravedadSancion;
use App\Models\Auditoria;
use App\Models\ConfiguracionSuscripcion;
use App\Models\Empresa;
use App\Models\PagoSuscripcion;
use App\Models\User;
use App\Services\Reputacion\ReputationService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const TESORERIA = '0x9999999999999999999999999999999999999999';
const USDC_AMOY_PLAN = '0x41E94Eb019C0762f9Bfcf9Fb1E58725BfB0e7582';

function configurarPlan(?string $tesoreria = TESORERIA, float $precio = 5, int $dias = 30): void
{
    ConfiguracionSuscripcion::query()->create(['tesoreria' => $tesoreria, 'precio_usdc' => $precio, 'dias' => $dias]);
}

/** Recibo de una transferencia de USDC como lo devuelve el nodo. */
function reciboPlan(string $destino, int $unidades, string $token = USDC_AMOY_PLAN, int $bloque = 998): array
{
    $palabra = fn (string $hex): string => '0x'.str_pad(ltrim(preg_replace('/^0x/', '', strtolower($hex)), '0'), 64, '0', STR_PAD_LEFT);

    return [
        'status' => '0x1',
        'blockNumber' => '0x'.dechex($bloque),
        'from' => '0x1111111111111111111111111111111111111111',
        'logs' => [[
            'address' => strtolower($token),
            'topics' => ['0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef', $palabra('0x1111111111111111111111111111111111111111'), $palabra($destino)],
            'data' => $palabra(dechex($unidades)),
        ]],
    ];
}

function nodoFalso(?array $recibo, int $ultimoBloque = 1000): void
{
    Http::swap(new Factory(app('events')));
    Http::fake(fn (Request $request) => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => match ($request['method']) {
        'eth_getTransactionReceipt' => $recibo,
        'eth_blockNumber' => '0x'.dechex($ultimoBloque),
        default => null,
    }]));
}

function hashPlan(string $c): string
{
    return '0x'.str_repeat($c, 64);
}

/** @return array{0: User, 1: Empresa} */
function duenaDeEmpresa(): array
{
    $duena = propietarioDeEmpresa();

    return [$duena, $duena->empresas()->firstOrFail()];
}

// ---------------------------------------------------------------------
// Pagar el plan
// ---------------------------------------------------------------------

test('la empresa paga 5 USDC a la tesoreria y su Plan Profesional queda activo 30 dias', function () {
    configurarPlan();
    [$duena, $empresa] = duenaDeEmpresa();
    nodoFalso(reciboPlan(TESORERIA, 5_000_000));

    $this->actingAs($duena)->post(route('empresa.plan.pagar'), ['tx_hash' => hashPlan('a')])->assertSessionHasNoErrors();

    $empresa->refresh();
    $pago = PagoSuscripcion::query()->firstOrFail();
    expect($empresa->esPlanProfesional())->toBeTrue()
        ->and($empresa->plan_expira_en->isSameDay(now()->addDays(30)))->toBeTrue()
        ->and($pago->estado)->toBe('confirmado')
        ->and($pago->monto)->toBe(5.0)
        ->and($pago->wallet_destino)->toBe(TESORERIA)
        ->and(Auditoria::query()->where('accion', 'empresas.plan_pagado')->exists())->toBeTrue()
        ->and($duena->notifications()->get()->pluck('data.titulo'))->toContain('Pago del Plan Profesional confirmado');
});

test('renovar antes de vencer suma los dias al vencimiento actual', function () {
    configurarPlan();
    [$duena, $empresa] = duenaDeEmpresa();
    $empresa->update(['plan' => 'profesional', 'plan_expira_en' => now()->addDays(10)]);
    nodoFalso(reciboPlan(TESORERIA, 5_000_000));

    $this->actingAs($duena)->post(route('empresa.plan.pagar'), ['tx_hash' => hashPlan('b')]);

    expect($empresa->fresh()->plan_expira_en->isSameDay(now()->addDays(40)))->toBeTrue();
});

test('un pago que no cumple no activa el plan', function (array $recibo, string $motivo) {
    configurarPlan();
    [$duena, $empresa] = duenaDeEmpresa();
    nodoFalso($recibo);

    $this->actingAs($duena)->post(route('empresa.plan.pagar'), ['tx_hash' => hashPlan('c')]);

    expect(PagoSuscripcion::query()->firstOrFail())->estado->toBe('fallido')->error->toContain($motivo)
        ->and($empresa->fresh()->esPlanProfesional())->toBeFalse();
})->with([
    'a otra wallet' => [fn () => reciboPlan('0x2222222222222222222222222222222222222222', 5_000_000), 'no transfiere USDC'],
    'monto menor' => [fn () => reciboPlan(TESORERIA, 4_990_000), 'Monto insuficiente'],
    'token falso' => [fn () => reciboPlan(TESORERIA, 5_000_000, token: '0x3333333333333333333333333333333333333333'), 'no transfiere USDC'],
]);

test('sin confirmaciones suficientes queda verificando y se confirma al comprobar', function () {
    configurarPlan();
    [$duena, $empresa] = duenaDeEmpresa();
    nodoFalso(reciboPlan(TESORERIA, 5_000_000, bloque: 1000), 1000);

    $this->actingAs($duena)->post(route('empresa.plan.pagar'), ['tx_hash' => hashPlan('d')]);
    $pago = PagoSuscripcion::query()->firstOrFail();
    expect($pago->estado)->toBe('verificando');

    // Mientras tanto no se puede registrar otro pago.
    $this->post(route('empresa.plan.pagar'), ['tx_hash' => hashPlan('e')])->assertSessionHasErrors('tx_hash');

    nodoFalso(reciboPlan(TESORERIA, 5_000_000, bloque: 1000), 1005);
    $this->post(route('empresa.plan.comprobar', $pago))->assertRedirect();

    expect($pago->fresh()->estado)->toBe('confirmado')->and($empresa->fresh()->esPlanProfesional())->toBeTrue();
});

test('sin wallet de tesoreria configurada no se puede pagar', function () {
    [$duena] = duenaDeEmpresa();

    $this->actingAs($duena)->post(route('empresa.plan.pagar'), ['tx_hash' => hashPlan('f')])->assertSessionHasErrors('tx_hash');
    $this->get(route('empresa.plan'))->assertInertia(fn (Assert $p) => $p->where('plan.tesoreria', null)->where('plan.precio', 5));
});

test('una transaccion no sirve dos veces: ni para otro plan ni para un bounty', function () {
    configurarPlan();
    [$duena] = duenaDeEmpresa();
    [$otra] = duenaDeEmpresa();
    nodoFalso(reciboPlan(TESORERIA, 5_000_000));

    $this->actingAs($duena)->post(route('empresa.plan.pagar'), ['tx_hash' => hashPlan('1')])->assertSessionHasNoErrors();
    $this->actingAs($otra)->post(route('empresa.plan.pagar'), ['tx_hash' => hashPlan('1')])->assertSessionHasErrors('tx_hash');

    $programa = programaDeEmpresa($duena, ['tiene_recompensas' => true]);
    $reporte = reporteDe(investigador(['wallet_address' => '0x71c83638372331e200c025434d31b057c9a63974']), $programa, ['estado' => 'validado', 'bounty_monto' => 5, 'bounty_estado' => 'asignado']);
    $this->actingAs($duena)->post(route('reportes.bounty.transaccion', $reporte), ['tx_hash' => hashPlan('1')])->assertSessionHasErrors('tx_hash');
});

test('solo la empresa ve y paga su plan', function () {
    configurarPlan();
    [$duena] = duenaDeEmpresa();
    [$otra] = duenaDeEmpresa();
    nodoFalso(null);
    $this->actingAs($duena)->post(route('empresa.plan.pagar'), ['tx_hash' => hashPlan('2')]);
    $pago = PagoSuscripcion::query()->firstOrFail();

    $this->actingAs($otra)->post(route('empresa.plan.comprobar', $pago))->assertNotFound();
    $this->actingAs(investigador())->get(route('empresa.plan'))->assertForbidden();
});

// ---------------------------------------------------------------------
// Configuracion (admin)
// ---------------------------------------------------------------------

test('el admin configura la tesoreria con su contrasena; queda auditado y avisa a los admins', function () {
    $admin = administrador(['password' => 'clave-segura-123']);
    $otroAdmin = administrador();

    $this->actingAs($admin)->put(route('admin.config.plan.update'), ['tesoreria' => TESORERIA, 'precio_usdc' => 5, 'dias' => 30, 'password' => 'incorrecta'])
        ->assertSessionHasErrors('password');
    $this->put(route('admin.config.plan.update'), ['tesoreria' => '0xmal', 'precio_usdc' => 5, 'dias' => 30, 'password' => 'clave-segura-123'])
        ->assertSessionHasErrors('tesoreria');

    $this->put(route('admin.config.plan.update'), ['tesoreria' => '0xAbCdEf0123456789aBcDeF0123456789AbCdEf01', 'precio_usdc' => 7.5, 'dias' => 60, 'password' => 'clave-segura-123'])
        ->assertSessionHasNoErrors();

    $config = ConfiguracionSuscripcion::query()->firstOrFail();
    expect($config->tesoreria)->toBe('0xabcdef0123456789abcdef0123456789abcdef01')
        ->and($config->precio_usdc)->toBe(7.5)
        ->and($config->dias)->toBe(60)
        ->and(Auditoria::query()->where('accion', 'config.suscripcion_actualizada')->value('detalle')['tesoreria'] ?? null)->toBe('0xabcdef0123456789abcdef0123456789abcdef01')
        ->and($otroAdmin->notifications()->get()->pluck('data.titulo'))->toContain('Cambió la wallet de tesorería');
});

test('solo el admin ve la configuracion del plan y los ingresos', function () {
    configurarPlan();
    [$duena] = duenaDeEmpresa();

    $this->actingAs($duena)->get(route('admin.config.plan'))->assertForbidden();
    $this->actingAs($duena)->get(route('admin.ingresos'))->assertForbidden();
    $this->actingAs($duena)->put(route('admin.config.plan.update'), ['tesoreria' => null, 'precio_usdc' => 1, 'dias' => 1, 'password' => 'password'])->assertForbidden();

    $this->actingAs(administrador())->get(route('admin.ingresos'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/ingresos/Index')->where('resumen.tesoreria', TESORERIA));
});

// ---------------------------------------------------------------------
// Vencimiento
// ---------------------------------------------------------------------

test('se avisa una sola vez antes de vencer y al vencer vuelve a Comunitario sin tocar sus programas', function () {
    [$duena, $empresa] = duenaDeEmpresa();
    $empresa->update(['plan' => 'profesional', 'plan_expira_en' => now()->addDays(3)]);
    $privado = programaDeEmpresa($duena, ['es_publico' => false, 'estado' => 'activo']);

    $this->artisan('suscripciones:revisar-vencimientos')->assertSuccessful();
    $this->artisan('suscripciones:revisar-vencimientos')->assertSuccessful();
    expect($duena->notifications()->get()->pluck('data.titulo')->filter(fn ($t) => $t === 'Tu Plan Profesional vence pronto'))->toHaveCount(1);

    $this->travel(4)->days();
    $this->artisan('suscripciones:revisar-vencimientos')->assertSuccessful();

    expect($empresa->fresh()->plan)->toBe('comunitario')
        ->and($privado->fresh()->estado->value)->toBe('activo')
        ->and($duena->notifications()->get()->pluck('data.titulo'))->toContain('Tu Plan Profesional venció');
});

// ---------------------------------------------------------------------
// Investigadores verificados
// ---------------------------------------------------------------------

test('para ser verificado cuentan solo los informes confirmados por la empresa', function () {
    $investigador = investigador();
    reporteDe($investigador, null, ['estado' => 'validado']);
    reporteDe($investigador, null, ['estado' => 'en_reparacion']);
    reporteDe($investigador, null, ['estado' => 'cerrado']);
    expect($investigador->esVerificado())->toBeFalse();

    reporteDe($investigador, null, ['estado' => 'cerrado']);
    expect($investigador->esVerificado())->toBeTrue();
});

test('una sancion reciente impide ser verificado', function () {
    $investigador = investigador();
    foreach (range(1, 3) as $_) {
        reporteDe($investigador, null, ['estado' => 'cerrado']);
    }
    app(ReputationService::class)->aplicarSancion($investigador, 'reporte_duplicado', GravedadSancion::Leve);

    expect($investigador->fresh()->progresoVerificacion())
        ->es_verificado->toBeFalse()
        ->sancion_reciente->toBeTrue();
});

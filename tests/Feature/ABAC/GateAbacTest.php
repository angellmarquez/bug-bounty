<?php

use App\Abac\AccionesAbac;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

test('el Gate único abac permite la acción autorizada por la política', function () {
    $inv = investigador();
    $reporte = reporteDe($inv);

    expect(Gate::forUser($inv)->allows('abac', [AccionesAbac::ReporteVer, $reporte]))->toBeTrue();
});

test('el Gate único abac deniega la acción no permitida', function () {
    $otro = investigador();
    $reporte = reporteDe(investigador());

    expect(Gate::forUser($otro)->allows('abac', [AccionesAbac::ReporteVer, $reporte]))->toBeFalse();
});

test('el bypass administrativo pasa por el mismo Gate', function () {
    $admin = administrador();
    $reporte = reporteDe(investigador());

    expect(Gate::forUser($admin)->allows('abac', [AccionesAbac::ReporteVer, $reporte]))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('abac', [AccionesAbac::EmpresaVer]))->toBeTrue();
});

test('Gate::authorize lanza AuthorizationException al denegar', function () {
    $otro = investigador();
    $reporte = reporteDe(investigador());

    Gate::forUser($otro)->authorize('abac', [AccionesAbac::ReporteVer, $reporte]);
})->throws(AuthorizationException::class);

test('el Gate deniega a usuarios no autenticados sin invocar la política', function () {
    $reporte = reporteDe(investigador());

    expect(Gate::forUser(null)->allows('abac', [AccionesAbac::ReporteVer, $reporte]))->toBeFalse()
        ->and(Gate::allows('abac', [AccionesAbac::ReporteVer, $reporte]))->toBeFalse();
});

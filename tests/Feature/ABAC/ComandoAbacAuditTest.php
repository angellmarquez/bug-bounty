<?php

use Illuminate\Support\Facades\Artisan;

test('abac:audit lista las reglas configuradas', function () {
    $this->artisan('abac:audit')
        ->assertSuccessful()
        ->expectsOutputToContain('admin-bypass-total')
        ->expectsOutputToContain('deny_by_default')
        ->expectsOutputToContain('inv-ver-reporte-propio');
});

test('abac:audit evalúa una acción permitida con salida 0', function () {
    $inv = investigador();
    $reporte = reporteDe($inv);

    $this->artisan('abac:audit', [
        '--usuario' => (string) $inv->id,
        '--accion' => 'reportes.ver',
        '--reporte' => (string) $reporte->id,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('PERMITIR')
        ->expectsOutputToContain('inv-ver-reporte-propio');
});

test('abac:audit evalúa una acción denegada con salida 1', function () {
    $otro = investigador();
    $reporte = reporteDe(investigador());

    $this->artisan('abac:audit', [
        '--usuario' => (string) $otro->id,
        '--accion' => 'reportes.ver',
        '--reporte' => (string) $reporte->id,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('DENEGAR');
});

test('abac:audit refleja el deny explícito de triaje a investigadores', function () {
    $inv = investigador();
    $reporte = reporteDe($inv, atributos: ['estado' => 'en_revision']);

    $this->artisan('abac:audit', [
        '--usuario' => (string) $inv->id,
        '--accion' => 'reportes.validar',
        '--reporte' => (string) $reporte->id,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('denegar-triaje-a-investigador');
});

test('abac:audit permite evaluar como invitado sin objeto', function () {
    $this->artisan('abac:audit', ['--accion' => 'reportes.ver'])
        ->assertExitCode(1);
});

test('abac:audit falla ante un objeto inexistente', function () {
    Artisan::call('abac:audit', [
        '--accion' => 'reportes.ver',
        '--reporte' => '999999',
    ]);
})->throws(InvalidArgumentException::class);

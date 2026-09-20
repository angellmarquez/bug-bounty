<?php

use App\Enums\EstadoApelacion;
use App\Enums\EstadoReporte;
use App\Enums\EstadoSancion;
use App\Enums\TipoEventoReporte;
use App\Enums\TipoObjetivo;
use App\Models\Apelacion;
use App\Models\Auditoria;
use App\Models\EntradaReputacion;
use App\Models\EventoReporte;
use App\Models\ObjetivoPrograma;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\Rol;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('todas las tablas del dominio existen', function () {
    $tablas = [
        'roles',
        'rol_usuario',
        'programas',
        'objetivos_programa',
        'reportes',
        'eventos_reporte',
        'claves_pgp_plataforma',
        'sanciones',
        'apelaciones',
        'ledger_reputacion',
        'auditorias',
    ];

    foreach ($tablas as $tabla) {
        expect(Schema::hasTable($tabla))->toBeTrue("Falta la tabla {$tabla}");
    }
});

test('la tabla usuarios expone la reputación', function () {
    expect(Schema::hasColumn('users', 'reputation_score'))->toBeTrue();
});

test('las relaciones principales del dominio se resuelven', function () {
    $programa = Programa::factory()->create();
    $objetivo = ObjetivoPrograma::factory()->create(['programa_id' => $programa->id]);

    $reporte = Reporte::factory()->create([
        'programa_id' => $programa->id,
        'investigador_id' => User::factory(),
    ]);
    $evento = EventoReporte::factory()->create(['reporte_id' => $reporte->id]);

    expect($programa->objetivos)->toHaveCount(1)
        ->and($objetivo->programa->is($programa))->toBeTrue()
        ->and($objetivo->tipo)->toBeInstanceOf(TipoObjetivo::class)
        ->and($reporte->programa->is($programa))->toBeTrue()
        ->and($reporte->estado)->toBe(EstadoReporte::Enviado)
        ->and($reporte->eventos->first()->is($evento))->toBeTrue()
        ->and($evento->reporte->is($reporte))->toBeTrue()
        ->and($evento->tipo)->toBeInstanceOf(TipoEventoReporte::class);
});

test('un usuario puede tener varios roles', function () {
    $usuario = User::factory()->create();
    $roles = Rol::factory()->count(2)->create();

    $usuario->roles()->attach($roles);

    expect($usuario->roles)->toHaveCount(2);
});

test('la cadena sanción-apelación-ledger y auditoría se resuelven', function () {
    $usuario = User::factory()->create();
    $reporte = Reporte::factory()->create(['investigador_id' => $usuario->id]);

    $sancion = Sancion::factory()->paraReporte($reporte)->create(['usuario_id' => $usuario->id]);
    $apelacion = Apelacion::factory()->aprobada()->create([
        'sancion_id' => $sancion->id,
        'usuario_id' => $usuario->id,
    ]);
    $entrada = EntradaReputacion::factory()->negativa()->create([
        'usuario_id' => $usuario->id,
        'reporte_id' => $reporte->id,
        'sancion_id' => $sancion->id,
        'apelacion_id' => $apelacion->id,
    ]);
    $auditoria = Auditoria::factory()->create(['usuario_id' => $usuario->id]);

    expect($usuario->sanciones)->toHaveCount(1)
        ->and($sancion->estado)->toBe(EstadoSancion::Aplicada)
        ->and($sancion->apelaciones->first()->is($apelacion))->toBeTrue()
        ->and($apelacion->estado)->toBe(EstadoApelacion::Aprobada)
        ->and($apelacion->sancion->is($sancion))->toBeTrue()
        ->and($entrada->es_negativa)->toBeTrue()
        ->and($entrada->sancion->is($sancion))->toBeTrue()
        ->and($entrada->apelacion->is($apelacion))->toBeTrue()
        ->and($usuario->entradasReputacion)->toHaveCount(1)
        ->and($usuario->auditorias->first()->is($auditoria))->toBeTrue();
});

<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ClavePgpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProgramaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReputacionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('reportes/crear', [ReporteController::class, 'create'])->name('reportes.create');
    Route::post('reportes', [ReporteController::class, 'store'])->name('reportes.store');
    Route::get('reportes/{reporte}/editar', [ReporteController::class, 'edit'])->name('reportes.edit');
    Route::put('reportes/{reporte}', [ReporteController::class, 'update'])->name('reportes.update');
    Route::post('reportes/{reporte}/enviar', [ReporteController::class, 'enviar'])->name('reportes.enviar');

    // Acciones de triaje (Slice 5.4)
    Route::post('reportes/{reporte}/asignar', [ReporteController::class, 'asignar'])->name('reportes.asignar');
    Route::post('reportes/{reporte}/validar', [ReporteController::class, 'validar'])->name('reportes.validar');
    Route::post('reportes/{reporte}/rechazar', [ReporteController::class, 'rechazar'])->name('reportes.rechazar');
    Route::post('reportes/{reporte}/marcar-duplicado', [ReporteController::class, 'marcarDuplicado'])->name('reportes.marcar-duplicado');
    Route::post('reportes/{reporte}/pagar', [ReporteController::class, 'pagar'])->name('reportes.pagar');
    Route::post('reportes/{reporte}/cerrar', [ReporteController::class, 'cerrar'])->name('reportes.cerrar');
    Route::post('reportes/{reporte}/comentar', [ReporteController::class, 'comentar'])->name('reportes.comentar');

    Route::get('reportes/{reporte}', [ReporteController::class, 'show'])->name('reportes.show');

    // Programas (Slice 5.5)
    Route::get('programas', [ProgramaController::class, 'index'])->name('programas.index');
    Route::get('programas/{programa}', [ProgramaController::class, 'show'])->name('programas.show');
    Route::post('programas', [ProgramaController::class, 'store'])->name('programas.store');
    Route::put('programas/{programa}', [ProgramaController::class, 'update'])->name('programas.update');
    Route::delete('programas/{programa}', [ProgramaController::class, 'destroy'])->name('programas.destroy');
    Route::post('programas/{programa}/cambiar-estado', [ProgramaController::class, 'cambiarEstado'])->name('programas.cambiar-estado');
    Route::get('gestion/programas', [ProgramaController::class, 'gestion'])->name('programas.gestion');
    Route::get('gestion/programas/crear', [ProgramaController::class, 'create'])->name('programas.create');
    Route::get('gestion/programas/{programa}/editar', [ProgramaController::class, 'edit'])->name('programas.edit');

    // Admin (Slice 5.6)
    Route::get('admin/usuarios', [AdminController::class, 'usuarios'])->name('admin.usuarios');
    Route::put('admin/usuarios/{user}', [AdminController::class, 'updateUsuario'])->name('admin.usuarios.update');
    Route::get('admin/sanciones', [AdminController::class, 'sanciones'])->name('admin.sanciones');
    Route::post('admin/sanciones/{sancion}/revocar', [AdminController::class, 'revocarSancion'])->name('admin.sanciones.revocar');
    Route::get('admin/apelaciones', [AdminController::class, 'apelaciones'])->name('admin.apelaciones');
    Route::post('admin/apelaciones/{apelacion}/resolver', [AdminController::class, 'resolverApelacion'])->name('admin.apelaciones.resolver');
    Route::get('admin/auditoria', [AdminController::class, 'auditoria'])->name('admin.auditoria');

    // Config reputacion
    Route::get('admin/config/reputacion', [AdminController::class, 'configReputacion'])->name('admin.config.reputacion');
    Route::put('admin/config/reputacion', [AdminController::class, 'updateConfigReputacion'])->name('admin.config.reputacion.update');

    // PGP plataforma
    Route::post('admin/pgp/setup', [AdminController::class, 'pgpSetup'])->name('admin.pgp.setup');
    Route::get('admin/pgp', [AdminController::class, 'pgpEstado'])->name('admin.pgp');

    // Reputacion (investigador)
    Route::get('reputacion', [ReputacionController::class, 'ledger'])->name('reputacion.ledger');
    Route::get('reputacion/sanciones', [ReputacionController::class, 'sanciones'])->name('reputacion.sanciones');
    Route::get('reputacion/apelaciones', [ReputacionController::class, 'apelaciones'])->name('reputacion.apelaciones');
    Route::post('reputacion/sanciones/{sancion}/apelar', [ReputacionController::class, 'apelar'])->name('reputacion.apelar');

    // Claves PGP
    Route::get('claves-pgp', [ClavePgpController::class, 'index'])->name('claves-pgp.index');
    Route::post('claves-pgp', [ClavePgpController::class, 'registrar'])->name('claves-pgp.registrar');
    Route::post('claves-pgp/{clave}/verificar', [ClavePgpController::class, 'verificar'])->name('claves-pgp.verificar');
    Route::post('claves-pgp/{clave}/revocar', [ClavePgpController::class, 'revocar'])->name('claves-pgp.revocar');
});

require __DIR__.'/settings.php';

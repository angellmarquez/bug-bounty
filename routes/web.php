<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProgramaController;
use App\Http\Controllers\ReporteController;
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
});

require __DIR__.'/settings.php';

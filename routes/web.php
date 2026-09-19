<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReporteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('reportes/crear', [ReporteController::class, 'create'])->name('reportes.create');
    Route::post('reportes', [ReporteController::class, 'store'])->name('reportes.store');
    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('reportes/{reporte}/editar', [ReporteController::class, 'edit'])->name('reportes.edit');
    Route::put('reportes/{reporte}', [ReporteController::class, 'update'])->name('reportes.update');
    Route::post('reportes/{reporte}/enviar', [ReporteController::class, 'enviar'])->name('reportes.enviar');
    Route::get('reportes/{reporte}', [ReporteController::class, 'show'])->name('reportes.show');
});

require __DIR__.'/settings.php';

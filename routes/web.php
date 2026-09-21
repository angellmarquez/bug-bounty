<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApelacionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaAuthController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\ModeracionController;
use App\Http\Controllers\ProgramaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReputacionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('empresa/login', [EmpresaAuthController::class, 'login'])->name('empresa.login');
Route::get('empresa/registro', [EmpresaAuthController::class, 'create'])->name('empresa.register');
Route::post('empresa/registro', [EmpresaAuthController::class, 'store'])->name('empresa.register.store');
Route::middleware('auth')->get('empresa/invitacion/{token}', [EmpresaController::class, 'verInvitacion'])->name('empresa.invitacion');
Route::middleware('auth')->post('empresa/invitacion/{token}/aceptar', [EmpresaController::class, 'aceptarInvitacion'])->name('empresa.invitacion.aceptar');

Route::middleware('auth')->group(function () {
    Route::get('empresa', [EmpresaController::class, 'dashboard'])->name('empresa.dashboard');
    Route::get('empresa/reportes', [EmpresaController::class, 'reportes'])->name('empresa.reportes');
    Route::post('empresa/miembros', [EmpresaController::class, 'agregarMiembro'])->name('empresa.miembros.agregar');
    Route::post('empresa/invitaciones', [EmpresaController::class, 'invitarMiembro'])->name('empresa.invitaciones.crear');
    Route::delete('empresa/miembros/{user}', [EmpresaController::class, 'eliminarMiembro'])->name('empresa.miembros.eliminar');
});

Route::middleware(['auth', 'verified', 'empresa.access'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('reportes/crear', [ReporteController::class, 'create'])->name('reportes.create');
    Route::post('reportes', [ReporteController::class, 'store'])->name('reportes.store')->middleware('throttle:reportes');
    Route::get('reportes/{reporte}/editar', [ReporteController::class, 'edit'])->name('reportes.edit');
    Route::put('reportes/{reporte}', [ReporteController::class, 'update'])->name('reportes.update');
    Route::post('reportes/{reporte}/enviar', [ReporteController::class, 'enviar'])->name('reportes.enviar')->middleware('throttle:reportes');

    // Acciones de triaje (Slice 5.4)
    Route::get('reportes/{reporte}/vista-rapida', [ReporteController::class, 'vistaRapida'])->name('reportes.vista-rapida');
    Route::post('reportes/{reporte}/revisar', [ReporteController::class, 'revisar'])->name('reportes.revisar');
    Route::post('reportes/{reporte}/asignar', [ReporteController::class, 'asignar'])->name('reportes.asignar');
    Route::post('reportes/{reporte}/validar', [ReporteController::class, 'validar'])->name('reportes.validar');
    Route::post('reportes/{reporte}/rechazar', [ReporteController::class, 'rechazar'])->name('reportes.rechazar');
    Route::post('reportes/{reporte}/marcar-duplicado', [ReporteController::class, 'marcarDuplicado'])->name('reportes.marcar-duplicado');
    Route::post('reportes/{reporte}/reparacion', [ReporteController::class, 'reparacion'])->name('reportes.reparacion');
    Route::post('reportes/{reporte}/cerrar', [ReporteController::class, 'cerrar'])->name('reportes.cerrar');
    Route::post('reportes/{reporte}/comentar', [ReporteController::class, 'comentar'])->name('reportes.comentar');

    Route::get('reportes/{reporte}', [ReporteController::class, 'show'])->name('reportes.show');

    // Programas (Slice 5.5)
    Route::get('moderacion', [ModeracionController::class, 'index'])->name('moderacion.index');
    Route::get('moderacion/programas/{programa}', [ModeracionController::class, 'programa'])->name('moderacion.programa');

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
    // Las migas de pan "Admin" apuntan a /admin: sin esta ruta daba 404.
    Route::redirect('admin', '/admin/empresas')->name('admin.index');
    Route::get('admin/empresas', [AdminController::class, 'empresas'])->name('admin.empresas');
    Route::post('admin/empresas/{empresa}/aprobar', [AdminController::class, 'aprobarEmpresa'])->name('admin.empresas.aprobar');
    Route::post('admin/empresas/{empresa}/rechazar', [AdminController::class, 'rechazarEmpresa'])->name('admin.empresas.rechazar');
    Route::post('admin/empresas/{empresa}/suspender', [AdminController::class, 'suspenderEmpresa'])->name('admin.empresas.suspender');
    Route::post('admin/empresas/{empresa}/reactivar', [AdminController::class, 'reactivarEmpresa'])->name('admin.empresas.reactivar');
    Route::get('admin/moderadores', [AdminController::class, 'moderadores'])->name('admin.moderadores');
    Route::post('admin/moderadores/{user}', [AdminController::class, 'asignarModerador'])->name('admin.moderadores.asignar');
    Route::delete('admin/moderadores/{user}', [AdminController::class, 'revocarModerador'])->name('admin.moderadores.revocar');
    Route::post('admin/programas/{programa}/moderadores/{user}', [AdminController::class, 'asignarModeradorPrograma'])->name('admin.programas.moderadores.asignar');
    Route::delete('admin/programas/{programa}/moderadores/{user}', [AdminController::class, 'revocarModeradorPrograma'])->name('admin.programas.moderadores.revocar');
    Route::get('admin/usuarios', [AdminController::class, 'usuarios'])->name('admin.usuarios');
    Route::put('admin/usuarios/{user}', [AdminController::class, 'updateUsuario'])->name('admin.usuarios.update');
    Route::get('admin/sanciones', [AdminController::class, 'sanciones'])->name('admin.sanciones');
    Route::post('admin/sanciones/{sancion}/revocar', [AdminController::class, 'revocarSancion'])->name('admin.sanciones.revocar');
    // Las apelaciones las resuelven los moderadores y el administrador (nunca quien aplicó la sanción).
    Route::redirect('admin/apelaciones', '/moderacion/apelaciones')->name('admin.apelaciones');
    Route::get('moderacion/apelaciones', [ApelacionController::class, 'index'])->name('apelaciones.index');
    Route::get('moderacion/apelaciones/{apelacion}', [ApelacionController::class, 'show'])->name('apelaciones.show');
    Route::post('moderacion/apelaciones/{apelacion}/resolver', [ApelacionController::class, 'resolver'])->name('apelaciones.resolver');
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
    Route::get('reputacion/apelaciones/{apelacion}', [ReputacionController::class, 'apelacion'])->name('reputacion.apelacion');
    Route::post('reputacion/sanciones/{sancion}/apelar', [ReputacionController::class, 'apelar'])->name('reputacion.apelar');
});

require __DIR__.'/settings.php';

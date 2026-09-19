<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Models\Apelacion;
use App\Models\Auditoria;
use App\Models\ClavePgpPlataforma;
use App\Models\Rol;
use App\Models\Sancion;
use App\Models\User;
use App\Services\Pgp\PgpService;
use App\Services\Reputacion\ReputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminController extends Controller
{
    // ------------------------------------------------------------------
    // Usuarios
    // ------------------------------------------------------------------

    public function usuarios(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteCrear]);

        $query = User::query()->with('roles');

        if ($request->filled('rol')) {
            $query->whereHas('roles', fn ($q) => $q->where('slug', $request->input('rol')));
        }

        if ($request->filled('busqueda')) {
            $busqueda = $request->input('busqueda');
            $query->where(function ($q) use ($busqueda) {
                $q->where('name', 'like', "%{$busqueda}%")
                    ->orWhere('email', 'like', "%{$busqueda}%");
            });
        }

        $usuarios = $query->latest()->paginate(15)->withQueryString();

        $roles = Rol::orderBy('nombre')->get(['id', 'nombre', 'slug']);

        return Inertia::render('admin/usuarios/Index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'filtros' => $request->only(['rol', 'busqueda']),
        ]);
    }

    public function updateUsuario(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteCrear]);

        $request->validate([
            'rol' => ['required', 'string', 'exists:roles,slug'],
        ]);

        $rol = Rol::where('slug', $request->input('rol'))->first();
        abort_if($rol === null, 422, 'Rol no encontrado.');

        $user->roles()->sync([$rol->id]);

        Auditoria::query()->create([
            'usuario_id' => $request->user()->id,
            'accion' => 'admin.usuario.rol_cambiado',
            'entidad_type' => 'user',
            'entidad_id' => $user->id,
            'detalle' => ['rol_nuevo' => $rol->slug],
        ]);

        return redirect()->route('admin.usuarios')
            ->with('success', "Rol de {$user->name} actualizado a {$rol->nombre}.");
    }

    // ------------------------------------------------------------------
    // Sanciones
    // ------------------------------------------------------------------

    public function sanciones(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteCrear]);

        $query = Sancion::query()->with(['usuario', 'reporte']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('gravedad')) {
            $query->where('gravedad', $request->input('gravedad'));
        }

        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->input('usuario_id'));
        }

        $sanciones = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('admin/sanciones/Index', [
            'sanciones' => $sanciones,
            'filtros' => $request->only(['estado', 'gravedad', 'usuario_id']),
        ]);
    }

    public function revocarSancion(Sancion $sancion, Request $request, ReputationService $reputacion): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteCrear]);

        $request->validate([
            'nota' => ['required', 'string', 'max:2000'],
        ]);

        $reputacion->revocarSancion($sancion, $request->input('nota'));

        return redirect()->route('admin.sanciones')
            ->with('success', 'Sancion revocada exitosamente. Puntos devueltos al ledger.');
    }

    // ------------------------------------------------------------------
    // Apelaciones
    // ------------------------------------------------------------------

    public function apelaciones(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ApelacionResolver]);

        $query = Apelacion::query()->with(['sancion.usuario', 'usuario', 'resueltaPor']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $apelaciones = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('admin/apelaciones/Index', [
            'apelaciones' => $apelaciones,
            'filtros' => $request->only(['estado']),
        ]);
    }

    public function resolverApelacion(Apelacion $apelacion, Request $request, ReputationService $reputacion): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ApelacionResolver]);

        $validated = $request->validate([
            'aprobada' => ['required', 'boolean'],
            'nota' => ['required', 'string', 'max:2000'],
        ]);

        $reputacion->resolverApelacion(
            $apelacion,
            $validated['aprobada'],
            $request->user(),
            $validated['nota'],
        );

        $texto = $validated['aprobada'] ? 'aprobada' : 'rechazada';

        return redirect()->route('admin.apelaciones')
            ->with('success', "Apelacion {$texto}. {$apelacion->sancion->usuario->name}.");
    }

    // ------------------------------------------------------------------
    // Auditoria
    // ------------------------------------------------------------------

    public function auditoria(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteCrear]);

        $query = Auditoria::query()->with('usuario');

        if ($request->filled('accion')) {
            $query->where('accion', 'like', "%{$request->input('accion')}%");
        }

        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->input('usuario_id'));
        }

        if ($request->filled('entidad')) {
            $query->where('entidad_type', $request->input('entidad'));
        }

        $auditoria = $query->latest('created_at')->paginate(20)->withQueryString();

        $usuarios = User::select('id', 'name', 'email')
            ->whereHas('auditorias')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/auditoria/Index', [
            'auditoria' => $auditoria,
            'usuarios' => $usuarios,
            'filtros' => $request->only(['accion', 'usuario_id', 'entidad']),
        ]);
    }

    // ------------------------------------------------------------------
    // Config Reputacion
    // ------------------------------------------------------------------

    public function configReputacion(): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteCrear]);

        return Inertia::render('admin/config/Reputacion', [
            'config' => config('reputacion'),
        ]);
    }

    public function updateConfigReputacion(Request $request): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteCrear]);

        $validated = $request->validate([
            'puntos_inicial' => ['required', 'integer', 'min:0'],
            'puntos.reporte_validado' => ['required', 'integer', 'min:0'],
            'puntos.reporte_pagado' => ['required', 'integer', 'min:0'],
            'puntos.calidad_documentacion' => ['required', 'integer', 'min:0'],
            'puntos.participacion' => ['required', 'integer', 'min:0'],
            'penalizacion.leve' => ['required', 'integer'],
            'penalizacion.media' => ['required', 'integer'],
            'penalizacion.grave' => ['required', 'integer'],
            'suspension.leve.dias' => ['required', 'integer', 'min:0'],
            'suspension.media.dias' => ['required', 'integer', 'min:0'],
            'suspension.grave.dias' => ['required', 'integer', 'min:0'],
            'plazo_apelacion_dias' => ['required', 'integer', 'min:1'],
        ]);

        Auditoria::query()->create([
            'usuario_id' => $request->user()->id,
            'accion' => 'admin.config.reputacion_actualizada',
            'detalle' => ['config_anterior' => config('reputacion'), 'config_nueva' => $validated],
        ]);

        return redirect()->route('admin.config.reputacion')
            ->with('success', 'Configuracion de reputacion actualizada.');
    }

    // ------------------------------------------------------------------
    // PGP Plataforma
    // ------------------------------------------------------------------

    public function pgpEstado(): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteCrear]);

        $claveActiva = ClavePgpPlataforma::query()->where('activa', true)->first();
        $pgpService = app(PgpService::class);

        return Inertia::render('admin/pgp/Index', [
            'clave' => $claveActiva?->only([
                'id', 'huella', 'identidad', 'algoritmo', 'bits', 'creada_en', 'expira_en', 'activa',
            ]),
            'driver' => $pgpService->driverName(),
            'available' => $pgpService->available(),
        ]);
    }

    public function pgpSetup(Request $request, PgpService $pgpService): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteCrear]);

        try {
            $clave = $pgpService->generatePlatformKeyPair();

            Auditoria::query()->create([
                'usuario_id' => $request->user()->id,
                'accion' => 'admin.pgp.clave_generada',
                'entidad_type' => 'clave_pgp_plataforma',
                'entidad_id' => $clave->id,
                'detalle' => ['huella' => $clave->huella],
            ]);

            return redirect()->route('admin.pgp')
                ->with('success', "Par de claves PGP generado. Huella: {$clave->huella}");
        } catch (\Throwable $e) {
            return redirect()->route('admin.pgp')
                ->withErrors(['pgp' => 'Error generando claves: '.$e->getMessage()]);
        }
    }
}

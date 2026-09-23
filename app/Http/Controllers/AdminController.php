<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Enums\EstadoEmpresa;
use App\Mail\EmpresaEstadoMail;
use App\Models\Auditoria;
use App\Models\ClavePgpPlataforma;
use App\Models\ConfiguracionReputacion;
use App\Models\Empresa;
use App\Models\Programa;
use App\Models\Rol;
use App\Models\Sancion;
use App\Models\User;
use App\Services\Empresas\MembresiaEmpresa;
use App\Services\Notificaciones\Notificador;
use App\Services\Pgp\PgpService;
use App\Services\Reputacion\ReputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

class AdminController extends Controller
{
    // ------------------------------------------------------------------
    // Empresas
    // ------------------------------------------------------------------

    public function empresas(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::EmpresaVer]);

        $query = Empresa::query()->with(['usuarios', 'aprobador']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('busqueda')) {
            $busqueda = $request->input('busqueda');
            $query->where(function ($q) use ($busqueda) {
                $q->where('razon_social', 'like', "%{$busqueda}%")
                    ->orWhere('nombre_comercial', 'like', "%{$busqueda}%")
                    ->orWhere('identificador_fiscal', 'like', "%{$busqueda}%");
            });
        }

        return Inertia::render('admin/empresas/Index', [
            'empresas' => $query->latest()->paginate(15)->withQueryString(),
            'filtros' => $request->only(['estado', 'busqueda']),
        ]);
    }

    public function aprobarEmpresa(Empresa $empresa, Request $request): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::EmpresaAprobar, $empresa]);

        $validated = $request->validate([
            'motivo' => ['nullable', 'string', 'max:2000'],
        ]);

        $empresa->update([
            'estado' => EstadoEmpresa::Aprobada,
            'motivo_estado' => $validated['motivo'] ?? null,
            'aprobado_por' => $request->user()->id,
            'aprobado_en' => now(),
        ]);

        $this->registrarDecisionEmpresa($request, $empresa, 'admin.empresa.aprobada');
        app(Notificador::class)->empresaDecidida($empresa, 'aprobada');
        if (config('mail.enabled')) {
            Mail::to($empresa->email)->send(new EmpresaEstadoMail($empresa, 'aprobada'));
        }

        return redirect()->route('admin.empresas')->with('success', 'Empresa aprobada correctamente.');
    }

    public function rechazarEmpresa(Empresa $empresa, Request $request): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::EmpresaRechazar, $empresa]);

        $validated = $request->validate([
            'motivo' => ['required', 'string', 'max:2000'],
        ]);

        $empresa->update([
            'estado' => EstadoEmpresa::Rechazada,
            'motivo_estado' => $validated['motivo'],
            'aprobado_por' => $request->user()->id,
            'aprobado_en' => now(),
        ]);

        $this->registrarDecisionEmpresa($request, $empresa, 'admin.empresa.rechazada');
        app(Notificador::class)->empresaDecidida($empresa, 'rechazada');
        if (config('mail.enabled')) {
            Mail::to($empresa->email)->send(new EmpresaEstadoMail($empresa, 'rechazada'));
        }

        return redirect()->route('admin.empresas')->with('success', 'Empresa rechazada.');
    }

    public function suspenderEmpresa(Empresa $empresa, Request $request): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::EmpresaSuspender, $empresa]);

        $validated = $request->validate([
            'motivo' => ['required', 'string', 'max:2000'],
        ]);

        $empresa->update([
            'estado' => EstadoEmpresa::Suspendida,
            'motivo_estado' => $validated['motivo'],
        ]);

        $this->registrarDecisionEmpresa($request, $empresa, 'admin.empresa.suspendida');
        app(Notificador::class)->empresaDecidida($empresa, 'suspendida');
        if (config('mail.enabled')) {
            Mail::to($empresa->email)->send(new EmpresaEstadoMail($empresa, 'suspendida'));
        }

        return redirect()->route('admin.empresas')->with('success', 'Empresa suspendida.');
    }

    public function reactivarEmpresa(Empresa $empresa, Request $request): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::EmpresaReactivar, $empresa]);

        $empresa->update([
            'estado' => EstadoEmpresa::Aprobada,
            'motivo_estado' => null,
            'aprobado_por' => $request->user()->id,
            'aprobado_en' => now(),
        ]);

        $this->registrarDecisionEmpresa($request, $empresa, 'admin.empresa.reactivada');
        app(Notificador::class)->empresaDecidida($empresa, 'aprobada');
        if (config('mail.enabled')) {
            Mail::to($empresa->email)->send(new EmpresaEstadoMail($empresa, 'aprobada'));
        }

        return redirect()->route('admin.empresas')->with('success', 'Empresa reactivada.');
    }

    private function registrarDecisionEmpresa(Request $request, Empresa $empresa, string $accion): void
    {
        Auditoria::registrar($accion, $empresa, [
            'estado' => $empresa->estado->value,
            'motivo' => $empresa->motivo_estado,
        ], $request->user()->id);
    }

    // ------------------------------------------------------------------
    // Moderadores
    // ------------------------------------------------------------------

    public function moderadores(): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ModeradorAsignar]);

        $moderador = Rol::where('slug', 'moderador')->first();
        $moderadores = $moderador?->usuarios()->latest('users.created_at')->paginate(15) ?? User::query()->whereKey(0)->paginate(15);
        $usuariosDisponibles = User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', 'investigador'))
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('slug', ['administrador', 'moderador']))
            ->whereDoesntHave('empresas', fn ($query) => $query->where('empresa_usuario.estado', 'activo'))
            ->with('roles')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('slug')->values()->all(),
            ])
            ->values();

        return Inertia::render('admin/moderadores/Index', [
            'moderadores' => $moderadores,
            'usuariosDisponibles' => $usuariosDisponibles,
            'programas' => Programa::query()
                ->with('moderadores:id')
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
        ]);
    }

    public function asignarModerador(User $user, Request $request): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ModeradorAsignar]);

        abort_if($user->roles()->where('slug', 'administrador')->exists(), 422, 'Un administrador no puede asignarse como moderador.');

        if (app(MembresiaEmpresa::class)->pertenece($user)) {
            return redirect()->route('admin.moderadores')
                ->with('error', "{$user->name} forma parte de una empresa: un moderador no puede pertenecer a una empresa (conflicto de interés).");
        }

        $rol = Rol::firstOrCreate(
            ['slug' => 'moderador'],
            [
                'nombre' => 'Moderador',
                'descripcion' => 'Revisa reportes y modera operaciones asignadas.',
            ],
        );
        $user->roles()->syncWithoutDetaching([$rol->id]);

        $this->registrarDecisionModerador($request, $user, 'admin.moderador.asignado');
        app(Notificador::class)->moderadorRol($user, true);

        return redirect()->route('admin.moderadores')->with('success', 'Moderador asignado correctamente.');
    }

    public function revocarModerador(User $user, Request $request): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ModeradorRevocar]);

        $rol = Rol::where('slug', 'moderador')->first();
        if ($rol === null) {
            return redirect()->route('admin.moderadores');
        }
        $user->roles()->detach($rol->id);
        // Sin el rol no tiene sentido conservar los programas asignados.
        $user->programasModerados()->detach();

        $this->registrarDecisionModerador($request, $user, 'admin.moderador.revocado');
        app(Notificador::class)->moderadorRol($user, false);

        return redirect()->route('admin.moderadores')->with('success', 'Rol de moderador revocado.');
    }

    public function asignarModeradorPrograma(Programa $programa, User $user, Request $request): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ModeradorAsignar]);
        abort_unless($user->roles()->where('slug', 'moderador')->exists(), 422, 'El usuario no tiene rol de moderador.');

        // Quien ya reportó en un programa no puede moderarlo: revisaría (o vería) informes con conflicto de interés.
        if ($programa->reportes()->where('investigador_id', $user->id)->exists()) {
            return redirect()->route('admin.moderadores')
                ->with('error', "{$user->name} ya presentó informes en {$programa->nombre}: no puede moderarlo.");
        }

        $programa->moderadores()->syncWithoutDetaching([
            $user->id => ['asignado_por' => $request->user()->id],
        ]);

        $this->registrarDecisionModerador($request, $user, 'admin.moderador.programa.asignado');
        app(Notificador::class)->moderadorPrograma($user, $programa, true);

        return redirect()->route('admin.moderadores')->with('success', 'Moderador asignado al programa.');
    }

    public function revocarModeradorPrograma(Programa $programa, User $user, Request $request): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ModeradorRevocar]);
        $programa->moderadores()->detach($user->id);

        $this->registrarDecisionModerador($request, $user, 'admin.moderador.programa.revocado');
        app(Notificador::class)->moderadorPrograma($user, $programa, false);

        return redirect()->route('admin.moderadores')->with('success', 'Moderador retirado del programa.');
    }

    private function registrarDecisionModerador(Request $request, User $user, string $accion): void
    {
        Auditoria::registrar($accion, $user, ['usuario' => $user->email], $request->user()->id);
    }

    // ------------------------------------------------------------------
    // Usuarios
    // ------------------------------------------------------------------

    public function usuarios(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::UsuarioVer]);

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
        Gate::authorize('abac', [AccionesAbac::UsuarioActualizarRol]);

        $request->validate([
            'rol' => ['required', 'string', 'exists:roles,slug'],
        ]);

        $rol = Rol::where('slug', $request->input('rol'))->first();
        abort_if($rol === null, 422, 'Rol no encontrado.');

        if ($rol->slug === 'moderador' && app(MembresiaEmpresa::class)->pertenece($user)) {
            return redirect()->route('admin.usuarios')
                ->with('error', "{$user->name} forma parte de una empresa: un moderador no puede pertenecer a una empresa (conflicto de interés).");
        }

        // Cambiar el rol reemplaza al anterior: sin estas guardas un administrador podía
        // quitarse su propio rol de administrador (y dejar la plataforma sin ninguno).
        if ($user->is($request->user()) && $rol->slug !== 'administrador') {
            return redirect()->route('admin.usuarios')->with('error', 'No puedes cambiar tu propio rol: pídele a otro administrador que lo haga.');
        }

        $esAdministrador = $user->roles()->where('slug', 'administrador')->exists();
        $quedanAdministradores = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'administrador'))->whereKeyNot($user->id)->exists();

        if ($esAdministrador && $rol->slug !== 'administrador' && ! $quedanAdministradores) {
            return redirect()->route('admin.usuarios')->with('error', 'Debe existir al menos un administrador en la plataforma.');
        }

        $user->roles()->sync([$rol->id]);

        Auditoria::registrar('admin.usuario.rol_cambiado', $user, ['rol_nuevo' => $rol->slug], $request->user()->id);

        return redirect()->route('admin.usuarios')
            ->with('success', "Rol de {$user->name} actualizado a {$rol->nombre}.");
    }

    // ------------------------------------------------------------------
    // Sanciones
    // ------------------------------------------------------------------

    public function sanciones(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::SancionVer]);

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
        Gate::authorize('abac', [AccionesAbac::SancionRevocar]);

        $request->validate([
            'nota' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $reputacion->revocarSancion($sancion, $request->input('nota'));
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.sanciones')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.sanciones')
            ->with('success', 'Sanción revocada exitosamente. Puntos devueltos al ledger.');
    }

    // ------------------------------------------------------------------
    // Apelaciones
    // ------------------------------------------------------------------

    // ------------------------------------------------------------------
    // Auditoria
    // ------------------------------------------------------------------

    public function auditoria(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::AuditoriaVer]);

        $query = Auditoria::query()->with('usuario.roles');

        if ($request->filled('accion')) {
            $query->where('accion', 'like', "%{$request->input('accion')}%");
        }

        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->input('usuario_id'));
        }

        if ($request->filled('entidad')) {
            $query->where('entidad_type', $request->input('entidad'));
        }

        // "Sistema" son las entradas sin actor (ej. la clave PGP que se genera sola).
        if ($request->filled('rol')) {
            if ($request->input('rol') === 'sistema') {
                $query->whereNull('usuario_id');
            } else {
                $query->whereHas('usuario.roles', fn ($q) => $q->where('slug', $request->input('rol')));
            }
        }

        $auditoria = $query->latest('created_at')->paginate(20)->withQueryString();

        // Solo lo que necesita la vista: el rol se aplana a slugs, como en el resto de la app.
        $auditoria->through(fn (Auditoria $entrada) => [
            'id' => $entrada->id,
            'accion' => $entrada->accion,
            'entidad_type' => $entrada->entidad_type,
            'entidad_id' => $entrada->entidad_id,
            'detalle' => $entrada->detalle,
            'ip' => $entrada->ip,
            'created_at' => $entrada->created_at,
            'usuario' => $entrada->usuario === null ? null : [
                'id' => $entrada->usuario->id,
                'name' => $entrada->usuario->name,
                'roles' => $entrada->usuario->roles->pluck('slug')->all(),
            ],
        ]);

        $usuarios = User::select('id', 'name', 'email')
            ->whereHas('auditorias')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/auditoria/Index', [
            'auditoria' => $auditoria,
            'usuarios' => $usuarios,
            'filtros' => $request->only(['accion', 'usuario_id', 'entidad', 'rol']),
        ]);
    }

    // ------------------------------------------------------------------
    // Config Reputacion
    // ------------------------------------------------------------------

    public function configReputacion(): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ConfigReputacionVer]);

        return Inertia::render('admin/config/Reputacion', [
            'config' => config('reputacion'),
        ]);
    }

    public function updateConfigReputacion(Request $request): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ConfigReputacionActualizar]);

        $validated = $request->validate([
            'puntos_inicial' => ['required', 'integer', 'min:0'],
            'puntos.reporte_validado' => ['required', 'integer', 'min:0'],
            'puntos.reporte_resuelto' => ['required', 'integer', 'min:0'],
            'puntos.calidad_documentacion' => ['required', 'integer', 'min:0'],
            'puntos.participacion' => ['required', 'integer', 'min:0'],
            // Una "penalización" positiva sería en realidad un premio: se exige <= 0.
            'penalizacion.leve' => ['required', 'integer', 'max:0'],
            'penalizacion.media' => ['required', 'integer', 'max:0'],
            'penalizacion.grave' => ['required', 'integer', 'max:0'],
            'suspension.leve.dias' => ['required', 'integer', 'min:0'],
            'suspension.media.dias' => ['required', 'integer', 'min:0'],
            'suspension.grave.dias' => ['required', 'integer', 'min:0'],
            'plazo_apelacion_dias' => ['required', 'integer', 'min:1'],
        ]);

        $configAnterior = config('reputacion');

        // Esto es lo que hace que el formulario sea funcional: antes solo se auditaba
        // el cambio pero config('reputacion.*') nunca se tocaba (ver ReputacionServiceProvider,
        // que carga esta fila encima de los defaults en cada arranque de la app).
        ConfiguracionReputacion::query()->updateOrCreate(
            ['id' => 1],
            ConfiguracionReputacion::desdeArrayValidado($validated),
        );
        config(['reputacion' => array_replace_recursive((array) $configAnterior, $validated)]);

        Auditoria::query()->create([
            'usuario_id' => $request->user()->id,
            'accion' => 'admin.config.reputacion_actualizada',
            'detalle' => ['config_anterior' => $configAnterior, 'config_nueva' => $validated],
        ]);

        return redirect()->route('admin.config.reputacion')
            ->with('success', 'Configuración de reputación actualizada.');
    }

    // ------------------------------------------------------------------
    // PGP Plataforma
    // ------------------------------------------------------------------

    public function pgpEstado(): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ClavePgpPlataformaVer]);

        $claveActiva = ClavePgpPlataforma::query()->where('activa', true)->first();
        $pgpService = app(PgpService::class);

        return Inertia::render('admin/pgp/Index', [
            // Solo lo necesario para saber si el cifrado funciona: nunca la huella,
            // identidad ni otros metadatos de la clave (ver AGENTS.md: la clave privada
            // nunca se expone, y esta página tampoco necesita mostrar de más).
            'clave' => $claveActiva === null ? null : ['id' => $claveActiva->id, 'expira_en' => $claveActiva->expira_en],
            'driver' => $pgpService->driverName(),
            'available' => $pgpService->available(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Enums\EstadoEmpresa;
use App\Mail\EmpresaInvitacionMail;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\EmpresaInvitacion;
use App\Models\Reporte;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Response as InertiaResponse;

class EmpresaController extends Controller
{
    public function dashboard(Request $request): InertiaResponse
    {
        $empresa = $request->user()
            ->empresas()
            ->withPivot(['rol_interno', 'estado'])
            ->latest('empresas.created_at')
            ->first();

        abort_if($empresa === null, 403, 'Tu usuario no pertenece a una empresa.');

        // Los borradores del investigador no cuentan: la empresa solo ve informes enviados.
        $programas = $empresa->programas()
            ->withCount([
                'objetivos',
                'reportes as reportes_todos',
                'reportes as reportes_total' => fn ($query) => $query->where('estado', '!=', 'borrador'),
                'reportes as reportes_pendientes' => fn ($query) => $query->whereIn('estado', Reporte::ESTADOS_PENDIENTES),
                'reportes as reportes_aprobados' => fn ($query) => $query->whereIn('estado', Reporte::ESTADOS_APROBADOS),
                'reportes as reportes_rechazados' => fn ($query) => $query->whereIn('estado', Reporte::ESTADOS_RECHAZADOS),
            ])
            ->latest()
            ->get(['id', 'nombre', 'estado', 'es_publico']);

        return Inertia::render('empresa/Dashboard', [
            'empresa' => [
                ...$empresa->only([
                    'id', 'razon_social', 'nombre_comercial', 'identificador_fiscal', 'email', 'estado', 'motivo_estado',
                ]),
                'estado' => $empresa->estado->value,
                'rol_interno' => data_get($empresa->pivot, 'rol_interno'),
                'puedeOperar' => $empresa->estado === EstadoEmpresa::Aprobada,
                'programas' => $programas,
                'resumen' => [
                    'programas' => $programas->count(),
                    'reportes' => $programas->sum('reportes_total'),
                    'pendientes' => $programas->sum('reportes_pendientes'),
                    'aprobados' => $programas->sum('reportes_aprobados'),
                    'rechazados' => $programas->sum('reportes_rechazados'),
                ],
                'reportes' => $this->reportesRecientes($empresa),
                'usuarios' => $empresa->usuarios()->get(['users.id', 'name', 'email']),
                'invitaciones' => $empresa->invitaciones()->where('estado', 'pendiente')->latest()->get(['id', 'email', 'expira_en']),
            ],
        ]);
    }

    /**
     * Listado completo y paginado de informes recibidos, en formato compacto.
     * El contenido (descripción y PoC) se lee en la página de cada informe.
     */
    public function reportes(Request $request): InertiaResponse
    {
        $empresa = $request->user()
            ->empresas()
            ->where('empresa_usuario.estado', 'activo')
            ->latest('empresas.created_at')
            ->first();

        abort_if($empresa === null, 403, 'Tu usuario no pertenece a una empresa.');
        abort_unless($empresa->estado === EstadoEmpresa::Aprobada, 403, 'Tu empresa todavía no tiene acceso operativo.');

        $filtro = in_array($request->input('filtro'), ['todos', 'pendientes', 'aprobados', 'rechazados', 'cerrados'], true)
            ? (string) $request->input('filtro')
            : 'todos';
        $programaId = $request->filled('programa_id') ? (int) $request->input('programa_id') : null;

        $recibidos = fn () => Reporte::query()
            ->whereIn('programa_id', $empresa->programas()->select('programas.id'))
            ->where('estado', '!=', 'borrador');

        $reportes = $recibidos()
            ->with(['programa:id,nombre', 'investigador:id,name,reputation_score'])
            ->when($programaId !== null, fn ($query) => $query->where('programa_id', $programaId))
            ->when($request->filled('busqueda'), function ($query) use ($request) {
                $busqueda = (string) $request->input('busqueda');
                $query->where(fn ($q) => $q->where('titulo', 'like', "%{$busqueda}%")->orWhere('numero_reporte', 'like', "%{$busqueda}%"));
            })
            ->when($filtro === 'pendientes', fn ($query) => $query->whereIn('estado', Reporte::ESTADOS_PENDIENTES))
            ->when($filtro === 'aprobados', fn ($query) => $query->whereIn('estado', Reporte::ESTADOS_APROBADOS))
            ->when($filtro === 'rechazados', fn ($query) => $query->whereIn('estado', Reporte::ESTADOS_RECHAZADOS))
            ->when($filtro === 'cerrados', fn ($query) => $query->where('estado', 'cerrado'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Reporte $reporte): array => $this->reporteCompacto($reporte));

        return Inertia::render('empresa/Reportes', [
            'empresa' => ['id' => $empresa->id, 'nombre' => $empresa->nombre_comercial ?? $empresa->razon_social],
            'programas' => $empresa->programas()->orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => [
                'filtro' => $filtro,
                'programa_id' => $programaId,
                'busqueda' => (string) $request->input('busqueda', ''),
            ],
            'conteos' => [
                'todos' => $recibidos()->count(),
                'pendientes' => $recibidos()->whereIn('estado', Reporte::ESTADOS_PENDIENTES)->count(),
                'aprobados' => $recibidos()->whereIn('estado', Reporte::ESTADOS_APROBADOS)->count(),
                'rechazados' => $recibidos()->whereIn('estado', Reporte::ESTADOS_RECHAZADOS)->count(),
                'cerrados' => $recibidos()->where('estado', 'cerrado')->count(),
            ],
            'reportes' => $reportes,
        ]);
    }

    /**
     * Los últimos informes recibidos, para la vista rápida del panel.
     *
     * @return array<int, array<string, mixed>>
     */
    private function reportesRecientes(Empresa $empresa): array
    {
        return Reporte::query()
            ->whereIn('programa_id', $empresa->programas()->select('programas.id'))
            ->where('estado', '!=', 'borrador')
            ->with(['programa:id,nombre', 'investigador:id,name,reputation_score'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Reporte $reporte): array => $this->reporteCompacto($reporte))
            ->values()
            ->all();
    }

    /**
     * Datos mínimos para las listas: sin descripción ni PoC.
     *
     * @return array<string, mixed>
     */
    private function reporteCompacto(Reporte $reporte): array
    {
        return [
            'id' => $reporte->id,
            'numero_reporte' => $reporte->numero_reporte,
            'titulo' => $reporte->titulo,
            'estado' => $reporte->estado->value,
            'severidad' => $reporte->severidad?->value,
            'programa_nombre' => $reporte->programa->nombre,
            'enviado_en' => ($reporte->enviado_en ?? $reporte->created_at)?->toISOString(),
            'investigador' => [
                'id' => $reporte->investigador->id,
                'name' => $reporte->investigador->name,
                'reputation_score' => $reporte->investigador->reputation_score,
            ],
        ];
    }

    public function invitarMiembro(Request $request): RedirectResponse
    {
        [$empresa, $usuarioActual] = $this->empresaActual($request);
        $this->autorizarMiembros($empresa, $usuarioActual);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $invitacion = EmpresaInvitacion::query()
            ->where('empresa_id', $empresa->id)
            ->where('email', $validated['email'])
            ->where('estado', 'pendiente')
            ->where('expira_en', '>', now())
            ->first();

        if ($invitacion === null) {
            $invitacion = EmpresaInvitacion::create([
                'empresa_id' => $empresa->id,
                'email' => $validated['email'],
                'token' => Str::random(64),
                'rol_interno' => 'miembro',
                'estado' => 'pendiente',
                'invitado_por' => $usuarioActual->id,
                'expira_en' => now()->addDays(7),
            ]);
        }

        if (config('mail.enabled')) {
            Mail::to($invitacion->email)->send(new EmpresaInvitacionMail(
                $invitacion->load('empresa'),
                route('empresa.invitacion', $invitacion->token),
            ));
        }

        $this->auditarMiembro($usuarioActual, $usuarioActual, $empresa, 'empresa.invitacion.creada');

        return redirect()->route('empresa.dashboard')->with('invitacion_url', route('empresa.invitacion', $invitacion->token));
    }

    public function verInvitacion(string $token): Response
    {
        $invitacion = EmpresaInvitacion::query()
            ->with('empresa:id,razon_social,nombre_comercial')
            ->where('token', $token)
            ->where('estado', 'pendiente')
            ->firstOrFail();

        return Inertia::render('empresa/Invitacion', [
            'invitacion' => [
                'token' => $invitacion->token,
                'email' => $invitacion->email,
                'expira_en' => $invitacion->expira_en->toISOString(),
                'empresa' => $invitacion->empresa->only(['razon_social', 'nombre_comercial']),
            ],
        ]);
    }

    public function aceptarInvitacion(Request $request, string $token): RedirectResponse
    {
        $invitacion = EmpresaInvitacion::query()
            ->where('token', $token)
            ->where('estado', 'pendiente')
            ->firstOrFail();

        abort_if($invitacion->expira_en->isPast(), 410, 'La invitación ha expirado.');
        if (strtolower((string) $request->user()->email) !== strtolower($invitacion->email)) {
            abort(403, 'La invitación no pertenece a este correo.');
        }

        $rolEmpresa = Rol::firstOrCreate(
            ['slug' => 'empresa'],
            ['nombre' => 'Empresa', 'descripcion' => 'Gestiona sus programas y recibe reportes de vulnerabilidades.'],
        );

        $invitacion->empresa->usuarios()->syncWithoutDetaching([
            $request->user()->id => [
                'rol_interno' => $invitacion->rol_interno,
                'estado' => 'activo',
                'aceptado_en' => now(),
            ],
        ]);
        $request->user()->roles()->syncWithoutDetaching([$rolEmpresa->id]);
        $invitacion->update(['estado' => 'aceptada', 'aceptado_en' => now()]);

        $this->auditarMiembro($request->user(), $request->user(), $invitacion->empresa, 'empresa.invitacion.aceptada');

        return redirect()->route('empresa.dashboard')->with('success', 'Invitación aceptada.');
    }

    public function agregarMiembro(Request $request): RedirectResponse
    {
        [$empresa, $usuarioActual] = $this->empresaActual($request);
        $this->autorizarMiembros($empresa, $usuarioActual);

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);
        $miembro = User::where('email', $validated['email'])->firstOrFail();

        abort_if($miembro->id === $usuarioActual->id, 422, 'El propietario ya pertenece a la empresa.');
        abort_if($empresa->usuarios()->whereKey($miembro->id)->exists(), 422, 'El usuario ya pertenece a la empresa.');

        $rolEmpresa = Rol::firstOrCreate(
            ['slug' => 'empresa'],
            ['nombre' => 'Empresa', 'descripcion' => 'Gestiona sus programas y recibe reportes de vulnerabilidades.'],
        );
        $miembro->roles()->syncWithoutDetaching([$rolEmpresa->id]);
        $empresa->usuarios()->attach($miembro, [
            'rol_interno' => 'miembro',
            'estado' => 'activo',
            'aceptado_en' => now(),
        ]);

        $this->auditarMiembro($usuarioActual, $miembro, $empresa, 'empresa.miembro.agregado');

        return redirect()->route('empresa.dashboard')->with('success', 'Miembro agregado.');
    }

    public function eliminarMiembro(Request $request, User $user): RedirectResponse
    {
        [$empresa, $usuarioActual] = $this->empresaActual($request);
        $this->autorizarMiembros($empresa, $usuarioActual);

        $pivot = $empresa->usuarios()->whereKey($user->id)->first()?->pivot;
        abort_if($pivot === null, 404, 'El usuario no pertenece a esta empresa.');
        abort_if(data_get($pivot, 'rol_interno') === 'propietario', 422, 'No se puede retirar al propietario.');

        $empresa->usuarios()->detach($user->id);
        $this->auditarMiembro($usuarioActual, $user, $empresa, 'empresa.miembro.eliminado');

        return redirect()->route('empresa.dashboard')->with('success', 'Miembro retirado.');
    }

    /** @return array{0: Empresa, 1: User} */
    private function empresaActual(Request $request): array
    {
        $user = $request->user();
        $empresa = $user->empresas()
            ->where('empresa_usuario.estado', 'activo')
            ->latest('empresas.created_at')
            ->first();

        abort_if($empresa === null, 403, 'Tu usuario no pertenece a una empresa activa.');

        return [$empresa, $user];
    }

    private function autorizarMiembros(Empresa $empresa, User $usuario): void
    {
        abort_if($empresa->estado !== EstadoEmpresa::Aprobada, 403, 'La empresa debe estar aprobada.');
        abort_if($empresa->usuarios()->whereKey($usuario->id)->wherePivot('rol_interno', 'propietario')->doesntExist(), 403, 'Solo el propietario puede gestionar miembros.');

        Gate::authorize('abac', [
            AccionesAbac::EmpresaGestionarMiembros,
            $empresa,
            ['empresa_id' => $empresa->id],
        ]);
    }

    private function auditarMiembro(User $actor, User $miembro, Empresa $empresa, string $accion): void
    {
        Auditoria::query()->create([
            'usuario_id' => $actor->id,
            'accion' => $accion,
            'entidad_type' => 'empresa',
            'entidad_id' => $empresa->id,
            'detalle' => ['usuario_id' => $miembro->id, 'email' => $miembro->email],
        ]);
    }
}

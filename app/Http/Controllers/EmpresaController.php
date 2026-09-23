<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Enums\EstadoEmpresa;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\EmpresaInvitacion;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Empresas\MembresiaEmpresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

class EmpresaController extends Controller
{
    public function dashboard(Request $request): InertiaResponse|RedirectResponse
    {
        $esAdmin = $this->esAdministrador($request->user());

        if ($esAdmin) {
            // El administrador no pertenece a ninguna empresa: entra al panel de la que elija.
            $empresa = $this->empresaElegida($request, 'empresa');

            if ($empresa === null) {
                return redirect()->route('admin.empresas')->with('error', 'Elige una empresa para abrir su panel.');
            }
        } else {
            $empresa = $request->user()
                ->empresas()
                ->withPivot(['rol_interno', 'estado'])
                ->latest('empresas.created_at')
                ->first();

            abort_if($empresa === null, 403, 'Tu usuario no pertenece a una empresa.');
        }

        $rolInterno = $esAdmin ? 'administrador' : data_get($empresa->pivot, 'rol_interno');

        // Un publicador (investigador invitado) no ve los informes de la empresa: su panel son los programas.
        if ($rolInterno === MembresiaEmpresa::PUBLICADOR) {
            return redirect()->route('programas.gestion');
        }
        $puedeGestionarMiembros = $empresa->estado === EstadoEmpresa::Aprobada && ($esAdmin || $rolInterno === 'propietario');

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
                'rol_interno' => $rolInterno,
                'esAdmin' => $esAdmin,
                'puedeOperar' => $empresa->estado === EstadoEmpresa::Aprobada,
                'puedeGestionarMiembros' => $puedeGestionarMiembros,
                'programas' => $programas,
                'resumen' => [
                    'programas' => $programas->count(),
                    'reportes' => $programas->sum('reportes_total'),
                    'pendientes' => $programas->sum('reportes_pendientes'),
                    'aprobados' => $programas->sum('reportes_aprobados'),
                    'rechazados' => $programas->sum('reportes_rechazados'),
                ],
                'reportes' => $this->reportesRecientes($empresa),
                'usuarios' => $empresa->usuarios()->get(['users.id', 'name', 'email'])
                    ->map(fn (User $usuario): array => [
                        'id' => $usuario->id,
                        'name' => $usuario->name,
                        'email' => $usuario->email,
                        'rol_interno' => $usuario->pivot->rol_interno,
                        'desde' => ($desde = $usuario->pivot->aceptado_en ?? $usuario->pivot->created_at) ? Carbon::parse($desde)->toISOString() : null,
                    ])->values()->all(),
                // Solo el propietario ve las invitaciones que hizo y puede cancelarlas.
                'invitaciones' => $puedeGestionarMiembros
                    ? $empresa->invitaciones()->with('usuario:id,name')->where('estado', 'pendiente')->where('expira_en', '>', now())->latest()->get()
                        ->map(fn (EmpresaInvitacion $invitacion): array => [
                            'id' => $invitacion->id,
                            'email' => $invitacion->email,
                            'nombre' => $invitacion->usuario?->name,
                            'expira_en' => $invitacion->expira_en->toISOString(),
                        ])->values()->all()
                    : [],
            ],
        ]);
    }

    /**
     * Listado completo y paginado de informes recibidos, en formato compacto.
     * El contenido (descripción y PoC) se lee en la página de cada informe.
     */
    public function reportes(Request $request): InertiaResponse
    {
        $esAdmin = $this->esAdministrador($request->user());
        $empresa = $esAdmin
            ? $this->empresaElegida($request, 'empresa')
            : $request->user()
                ->empresas()
                ->where('empresa_usuario.estado', 'activo')
                ->latest('empresas.created_at')
                ->first();

        abort_if($empresa === null, $esAdmin ? 404 : 403, $esAdmin ? 'Elige una empresa.' : 'Tu usuario no pertenece a una empresa.');
        abort_unless($empresa->estado === EstadoEmpresa::Aprobada, 403, 'Tu empresa todavía no tiene acceso operativo.');
        abort_if(! $esAdmin && data_get($empresa->pivot, 'rol_interno') !== MembresiaEmpresa::PROPIETARIO, 403, 'Solo el propietario de la empresa ve los informes que recibe.');

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
            'empresa' => ['id' => $empresa->id, 'nombre' => $empresa->nombre_comercial ?? $empresa->razon_social, 'esAdmin' => $esAdmin],
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

    public function invitarInvestigador(Request $request, MembresiaEmpresa $membresia): RedirectResponse
    {
        [$empresa, $usuarioActual] = $this->empresaActual($request);
        $this->autorizarMiembros($empresa, $usuarioActual);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $invitacion = $membresia->invitar($empresa, $usuarioActual, $validated['email']);
        } catch (InvalidArgumentException $e) {
            return $this->volverAlPanel($request, $empresa)->with('error', $e->getMessage());
        }

        $this->auditarMiembro($usuarioActual, $invitacion->usuario, $empresa, 'empresa.invitacion.creada');

        return $this->volverAlPanel($request, $empresa)
            ->with('success', "Invitación enviada a {$invitacion->usuario->name}: recibirá un aviso y podrá aceptarla o rechazarla (vale ".MembresiaEmpresa::VIGENCIA_DIAS.' días).');
    }

    public function cancelarInvitacion(Request $request, EmpresaInvitacion $invitacion, MembresiaEmpresa $membresia): RedirectResponse
    {
        $empresa = $invitacion->empresa;
        $this->autorizarMiembros($empresa, $request->user());

        try {
            $membresia->cancelar($invitacion);
        } catch (InvalidArgumentException $e) {
            return $this->volverAlPanel($request, $empresa)->with('error', $e->getMessage());
        }

        $this->auditarMiembro($request->user(), $invitacion->usuario, $empresa, 'empresa.invitacion.cancelada');

        return $this->volverAlPanel($request, $empresa)->with('success', 'Invitación cancelada.');
    }

    public function retirarMiembro(Request $request, User $user, MembresiaEmpresa $membresia): RedirectResponse
    {
        [$empresa, $usuarioActual] = $this->empresaActual($request);
        $this->autorizarMiembros($empresa, $usuarioActual);

        try {
            $membresia->retirar($empresa, $user);
        } catch (InvalidArgumentException $e) {
            return $this->volverAlPanel($request, $empresa)->with('error', $e->getMessage());
        }

        $this->auditarMiembro($usuarioActual, $user, $empresa, 'empresa.miembro.eliminado');

        return $this->volverAlPanel($request, $empresa)->with('success', 'Publicador retirado de la empresa.');
    }

    /** @return array{0: Empresa, 1: User} */
    private function empresaActual(Request $request): array
    {
        $user = $request->user();

        if ($this->esAdministrador($user)) {
            $empresa = $this->empresaElegida($request, 'empresa_id');
            abort_if($empresa === null, 422, 'Indica la empresa sobre la que actúas.');

            return [$empresa, $user];
        }

        $empresa = $user->empresas()
            ->where('empresa_usuario.estado', 'activo')
            ->latest('empresas.created_at')
            ->first();

        abort_if($empresa === null, 403, 'Tu usuario no pertenece a una empresa activa.');

        return [$empresa, $user];
    }

    private function esAdministrador(User $usuario): bool
    {
        return $usuario->roles()->where('slug', 'administrador')->exists();
    }

    /** Empresa indicada en la petición (solo la usa el administrador para operar cualquier empresa). */
    private function empresaElegida(Request $request, string $campo): ?Empresa
    {
        return $request->filled($campo) ? Empresa::query()->find((int) $request->input($campo)) : null;
    }

    private function volverAlPanel(Request $request, Empresa $empresa): RedirectResponse
    {
        return redirect()->route('empresa.dashboard', $this->esAdministrador($request->user()) ? ['empresa' => $empresa->id] : []);
    }

    private function autorizarMiembros(Empresa $empresa, User $usuario): void
    {
        abort_if($empresa->estado !== EstadoEmpresa::Aprobada, 403, 'La empresa debe estar aprobada.');
        abort_if(
            ! $this->esAdministrador($usuario) && $empresa->usuarios()->whereKey($usuario->id)->wherePivot('rol_interno', 'propietario')->doesntExist(),
            403,
            'Solo el propietario puede gestionar miembros.',
        );

        Gate::authorize('abac', [
            AccionesAbac::EmpresaGestionarMiembros,
            $empresa,
            ['empresa_id' => $empresa->id],
        ]);
    }

    private function auditarMiembro(User $actor, User $miembro, Empresa $empresa, string $accion): void
    {
        Auditoria::registrar($accion, $empresa, ['usuario_id' => $miembro->id, 'email' => $miembro->email], $actor->id);
    }
}

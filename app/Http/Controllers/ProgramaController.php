<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Http\Requests\StoreProgramaRequest;
use App\Http\Requests\UpdateProgramaRequest;
use App\Models\ObjetivoPrograma;
use App\Models\Programa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProgramaController extends Controller
{
    private const TRANSICIONES_VALIDAS = [
        'borrador' => ['activo', 'archivado'],
        'activo' => ['en_pausa', 'archivado'],
        'en_pausa' => ['activo', 'archivado'],
        'archivado' => [],
    ];

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $user->load('roles');

        $roles = $user->roles->pluck('slug')->toArray();
        $isAdmin = in_array('administrador', $roles);
        $isGestion = in_array('gestion', $roles);

        $query = Programa::query()->with(['creador', 'empresa', 'objetivos']);

        if ($isAdmin) {
            // Admin ve todos
        } elseif ($isGestion) {
            $query->gestionablesPor($user);
        } else {
            $query->visiblesPara($user);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('busqueda')) {
            $busqueda = $request->input('busqueda');
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('descripcion', 'like', "%{$busqueda}%");
            });
        }

        $programas = $query->orderBy('nombre')->paginate(15)->withQueryString();

        return Inertia::render('programas/Index', [
            'programas' => $programas,
            'filtros' => $request->only(['estado', 'busqueda']),
            'esGestion' => $isGestion || $isAdmin,
            'esAdmin' => $isAdmin,
        ]);
    }

    public function show(Programa $programa): InertiaResponse
    {
        $this->authorizeProgramAction(AccionesAbac::ProgramaVer, $programa);

        // Nunca se envía la relación `reportes`: contendría informes de otros investigadores.
        $programa->load(['creador', 'objetivos', 'empresa:id,razon_social,nombre_comercial,sitio_web']);
        $programa->loadCount(['reportes' => fn ($query) => $query->where('estado', '!=', 'borrador')]);

        $puedeReportar = Gate::allows('abac', [AccionesAbac::ReporteCrear, $programa]);
        $puedeGestionar = $this->puedeProgramAction(AccionesAbac::ProgramaGestionar, $programa);
        $puedeCambiarEstado = $this->puedeProgramAction(AccionesAbac::ProgramaCambiarEstado, $programa);
        $puedeEliminar = $this->puedeProgramAction(AccionesAbac::ProgramaEliminar, $programa);

        $transicionesPermitidas = $puedeCambiarEstado
            ? self::TRANSICIONES_VALIDAS[$programa->estado->value]
            : [];

        return Inertia::render('programas/Show', [
            'programa' => [
                ...$programa->toArray(),
                'empresa' => $programa->empresa === null ? null : [
                    'nombre' => $programa->empresa->nombre_comercial ?? $programa->empresa->razon_social,
                    'sitio_web' => $programa->empresa->sitio_web,
                ],
                // El autor solo es relevante para quien gestiona el programa.
                'creador' => $puedeGestionar ? $programa->creador?->only(['id', 'name']) : null,
                'objetivos' => $programa->objetivos->map(fn (ObjetivoPrograma $o) => $o->toArray()),
            ],
            'puedeReportar' => $puedeReportar,
            'puedeGestionar' => $puedeGestionar,
            'puedeCambiarEstado' => $puedeCambiarEstado,
            'puedeEliminar' => $puedeEliminar,
            'transicionesPermitidas' => $transicionesPermitidas,
        ]);
    }

    public function create(): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ProgramaCrear]);

        return Inertia::render('programas/gestion/Create');
    }

    public function edit(Programa $programa): InertiaResponse
    {
        $this->authorizeProgramAction(AccionesAbac::ProgramaGestionar, $programa);
        $programa->load(['objetivos']);

        return Inertia::render('programas/gestion/Edit', [
            'programa' => $programa,
        ]);
    }

    public function store(StoreProgramaRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $validated['reputacion_minima'] ??= 0;

        $programa = DB::transaction(function () use ($validated, $user) {
            $objetivos = $validated['objetivos'] ?? [];
            unset($validated['objetivos']);

            $validated['creado_por'] = $user->id;
            $validated['estado'] = 'borrador';

            if ($user->roles()->where('slug', 'empresa')->exists()) {
                $empresa = $user->empresas()
                    ->where('empresas.estado', 'aprobada')
                    ->where('empresa_usuario.estado', 'activo')
                    ->first();

                abort_if($empresa === null, 403, 'La empresa debe estar aprobada para crear programas.');
                $validated['empresa_id'] = $empresa->id;
            }

            $programa = Programa::create($validated);

            foreach ($objetivos as $objetivo) {
                $programa->objetivos()->create($objetivo);
            }

            return $programa;
        });

        return redirect()->route('programas.show', $programa)
            ->with('success', 'Programa creado exitosamente.');
    }

    public function update(UpdateProgramaRequest $request, Programa $programa): RedirectResponse
    {
        $validated = $request->validated();
        $objetivos = $validated['objetivos'] ?? null;
        unset($validated['objetivos']);

        DB::transaction(function () use ($programa, $validated, $objetivos) {
            $programa->update($validated);

            if ($objetivos !== null) {
                $programa->objetivos()->delete();
                foreach ($objetivos as $objetivo) {
                    $programa->objetivos()->create($objetivo);
                }
            }
        });

        return redirect()->route('programas.show', $programa)
            ->with('success', 'Programa actualizado exitosamente.');
    }

    public function destroy(Programa $programa): RedirectResponse
    {
        $this->authorizeProgramAction(AccionesAbac::ProgramaEliminar, $programa);

        $programa->delete();

        return redirect()->route('programas.index')
            ->with('success', 'Programa eliminado exitosamente.');
    }

    public function cambiarEstado(Request $request, Programa $programa): RedirectResponse
    {
        $this->authorizeProgramAction(AccionesAbac::ProgramaCambiarEstado, $programa);

        $request->validate([
            'estado' => ['required', 'string', 'in:activo,en_pausa,archivado'],
        ]);

        $estadoDestino = $request->input('estado');
        $estadoActual = $programa->estado->value;
        $permitidos = self::TRANSICIONES_VALIDAS[$estadoActual];

        abort_if(
            ! in_array($estadoDestino, $permitidos),
            422,
            "No se puede transitar de \"{$estadoActual}\" a \"{$estadoDestino}\"."
        );

        $programa->update(['estado' => $estadoDestino]);

        return redirect()->back(fallback: route('programas.show', $programa))
            ->with('success', "Programa cambiado a \"{$estadoDestino}\" exitosamente.");
    }

    private function authorizeProgramAction(string $accion, Programa $programa): void
    {
        Gate::authorize('abac', $this->argumentosAbac($accion, $programa));
    }

    private function puedeProgramAction(string $accion, Programa $programa): bool
    {
        return Gate::allows('abac', $this->argumentosAbac($accion, $programa));
    }

    /**
     * @return array{0: string, 1: Programa, 2: array{empresa_id?: int}}
     */
    private function argumentosAbac(string $accion, Programa $programa): array
    {
        $empresa = request()->user()?->empresas()
            ->where('empresas.estado', 'aprobada')
            ->where('empresa_usuario.estado', 'activo')
            ->first();

        return [$accion, $programa, $empresa === null ? [] : ['empresa_id' => $empresa->id]];
    }

    public function gestion(Request $request): InertiaResponse
    {
        $user = $request->user();
        $user->load('roles');

        $roles = $user->roles->pluck('slug')->toArray();
        $isAdmin = in_array('administrador', $roles);

        $query = Programa::query()->with(['creador', 'objetivos', 'reportes'])
            ->gestionablesPor($user);

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('busqueda')) {
            $busqueda = $request->input('busqueda');
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('descripcion', 'like', "%{$busqueda}%");
            });
        }

        $programas = $query->withCount('reportes')->orderBy('nombre')->paginate(15)->withQueryString();

        return Inertia::render('programas/gestion/Index', [
            'programas' => $programas,
            'filtros' => $request->only(['estado', 'busqueda']),
            'esAdmin' => $isAdmin,
        ]);
    }
}

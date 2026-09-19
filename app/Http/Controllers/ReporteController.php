<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Http\Requests\StoreReporteRequest;
use App\Http\Requests\UpdateReporteRequest;
use App\Models\ClavePgp;
use App\Models\Programa;
use App\Models\Reporte;
use App\Services\Pgp\PgpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReporteController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $user->load('roles');

        $roles = $user->roles->pluck('slug')->toArray();
        $isAdmin = in_array('administrador', $roles);
        $isGestion = in_array('gestion', $roles);

        $query = Reporte::query()
            ->with(['programa', 'investigador', 'asignadoA']);

        if ($isAdmin) {
            // Admin ve todos
        } elseif ($isGestion) {
            $query->where('estado', '!=', 'borrador');
        } else {
            $query->where('investigador_id', $user->id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('severidad')) {
            $query->where('severidad', $request->input('severidad'));
        }

        if ($request->filled('programa_id')) {
            $query->where('programa_id', $request->input('programa_id'));
        }

        if ($request->filled('busqueda')) {
            $busqueda = $request->input('busqueda');
            $query->where(function ($q) use ($busqueda) {
                $q->where('titulo', 'like', "%{$busqueda}%")
                    ->orWhere('numero_reporte', 'like', "%{$busqueda}%");
            });
        }

        $reportes = $query->latest()->paginate(15)->withQueryString();

        $programas = Programa::select('id', 'nombre')
            ->when(! $isAdmin && ! $isGestion, function ($q) {
                $q->where('estado', 'activo')->where('es_publico', true);
            })
            ->orderBy('nombre')
            ->get();

        return Inertia::render('reportes/Index', [
            'reportes' => $reportes,
            'filtros' => $request->only(['estado', 'severidad', 'programa_id', 'busqueda']),
            'programas' => $programas,
        ]);
    }

    public function show(Reporte $reporte): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteVer, $reporte]);

        $user = request()->user();
        $puedeVerNotasInternas = Gate::allows('abac', [AccionesAbac::ReporteVerNotasInternas, $reporte]);

        $reporte->load([
            'programa',
            'investigador',
            'asignadoA',
            'eventos.actor',
            'duplicadoDe',
        ]);

        if (! $puedeVerNotasInternas) {
            $reporte->setHidden(array_merge($reporte->getHidden(), ['notas_internas']));
        } else {
            $reporte->makeVisible('notas_internas');
        }

        $eventos = $reporte->eventos->map(fn ($evento) => [
            'id' => $evento->id,
            'reporte_id' => $evento->reporte_id,
            'tipo' => $evento->tipo->value,
            'actor_id' => $evento->actor_id,
            'descripcion' => $evento->nota,
            'metadata' => $evento->datos,
            'created_at' => $evento->created_at?->toISOString(),
            'actor' => $evento->actor?->only(['id', 'name']),
        ]);

        return Inertia::render('reportes/Show', [
            'reporte' => [
                ...$reporte->toArray(),
                'programa' => $reporte->programa->only(['id', 'nombre', 'slug']),
                'investigador' => $reporte->investigador->only(['id', 'name']),
                'asignadoA' => $reporte->asignadoA?->only(['id', 'name']),
                'duplicadoDe' => $reporte->duplicadoDe?->only(['id', 'numero_reporte', 'titulo']),
                'eventos' => $eventos,
            ],
            'puedeVerNotasInternas' => $puedeVerNotasInternas,
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $user = $request->user();

        $programas = Programa::where('estado', 'activo')
            ->where('es_publico', true)
            ->orderBy('nombre')
            ->get();

        $programaInicial = null;
        if ($request->filled('programa')) {
            $programaInicial = Programa::find($request->input('programa'));
        }

        $clavesPgp = ClavePgp::where('usuario_id', $user->id)
            ->where('estado', 'activa')
            ->get();

        return Inertia::render('reportes/Create', [
            'programas' => $programas->map(fn ($p) => $p->only(['id', 'nombre', 'slug', 'poc_schema'])),
            'programaInicial' => $programaInicial?->only(['id', 'nombre', 'slug', 'poc_schema']),
            'clavesPgp' => $clavesPgp->map(fn ($k) => $k->only([
                'id', 'huella', 'algoritmo', 'bits', 'es_principal',
            ])),
        ]);
    }

    public function store(StoreReporteRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $descripcion = $validated['descripcion'];

        if (! empty($validated['clave_pgp_id'])) {
            $clavePgp = ClavePgp::query()->find($validated['clave_pgp_id']);
            if (! $clavePgp instanceof ClavePgp || $clavePgp->usuario_id !== $user->id) {
                abort(403, 'No tienes permiso para usar esta clave PGP.');
            }
            $pgpService = app(PgpService::class);
            $descripcion = $pgpService->encrypt($descripcion, $clavePgp->huella);
        }

        $programa = Programa::query()->where('id', (int) $validated['programa_id'])->first();
        abort_if($programa === null, 404, 'Programa no encontrado.');

        $reporte = DB::transaction(function () use ($validated, $user, $descripcion, $programa) {
            $reporte = Reporte::create([
                'numero_reporte' => $this->generarNumeroReporte(),
                'programa_id' => $validated['programa_id'],
                'investigador_id' => $user->id,
                'titulo' => $validated['titulo'],
                'descripcion' => $descripcion,
                'categoria' => $validated['categoria'] ?? null,
                'vector_cvss' => $validated['vector_cvss'] ?? null,
                'puntuacion_cvss' => $validated['puntuacion_cvss'] ?? null,
                'severidad' => $validated['severidad'] ?? null,
                'poc' => $validated['poc'] ?? null,
                'estado' => 'borrador',
                'moneda' => $programa->moneda,
            ]);

            $reporte->eventos()->create([
                'actor_id' => $user->id,
                'tipo' => 'creado',
                'nota' => 'Reporte creado como borrador.',
            ]);

            return $reporte;
        });

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte creado exitosamente.');
    }

    public function edit(Reporte $reporte): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteEditar, $reporte]);

        $user = request()->user();

        $reporte->load('programa');

        $clavesPgp = ClavePgp::where('usuario_id', $user->id)
            ->where('estado', 'activa')
            ->get();

        return Inertia::render('reportes/Edit', [
            'reporte' => [
                ...$reporte->toArray(),
                'programa' => $reporte->programa->only(['id', 'nombre', 'slug', 'poc_schema']),
            ],
            'clavesPgp' => $clavesPgp->map(fn ($k) => $k->only([
                'id', 'huella', 'algoritmo', 'bits', 'es_principal',
            ])),
        ]);
    }

    public function update(UpdateReporteRequest $request, Reporte $reporte): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $camposActualizar = array_filter($validated, fn ($v) => $v !== 'clave_pgp_id');

        if (! empty($validated['clave_pgp_id']) && isset($camposActualizar['descripcion'])) {
            $clavePgp = ClavePgp::query()->find($validated['clave_pgp_id']);
            if (! $clavePgp instanceof ClavePgp || $clavePgp->usuario_id !== $user->id) {
                abort(403, 'No tienes permiso para usar esta clave PGP.');
            }
            $pgpService = app(PgpService::class);
            $camposActualizar['descripcion'] = $pgpService->encrypt(
                $camposActualizar['descripcion'],
                $clavePgp->huella
            );
        }

        unset($camposActualizar['clave_pgp_id']);
        $reporte->update($camposActualizar);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte actualizado exitosamente.');
    }

    public function enviar(Reporte $reporte): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteEnviar, $reporte]);

        $reporte->update([
            'estado' => 'enviado',
            'enviado_en' => now(),
        ]);

        $reporte->eventos()->create([
            'actor_id' => request()->user()->id,
            'tipo' => 'enviado',
            'nota' => 'Reporte enviado para revision.',
        ]);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte enviado exitosamente.');
    }

    private function generarNumeroReporte(): string
    {
        $year = date('Y');
        $ultimo = Reporte::where('numero_reporte', 'like', "BB-{$year}-%")
            ->orderByDesc('numero_reporte')
            ->value('numero_reporte');
        $consecutivo = $ultimo ? ((int) substr($ultimo, -4) + 1) : 1;

        return sprintf('BB-%s-%04d', $year, $consecutivo);
    }
}

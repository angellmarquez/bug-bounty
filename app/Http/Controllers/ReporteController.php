<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Enums\GravedadSancion;
use App\Http\Requests\StoreReporteRequest;
use App\Http\Requests\TransitionReporteRequest;
use App\Http\Requests\UpdateReporteRequest;
use App\Mail\SancionAplicadaMail;
use App\Models\ClavePgp;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Pgp\PgpService;
use App\Services\Reputacion\ReputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
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
        $isModerador = in_array('moderador', $roles);
        $isEmpresa = in_array('empresa', $roles);

        $query = Reporte::query()
            ->with(['programa', 'investigador', 'asignadoA']);

        if ($isAdmin) {
            // Admin ve todos
        } elseif ($isGestion) {
            $query->where('estado', '!=', 'borrador');
        } elseif ($isModerador) {
            $query->where('estado', '!=', 'borrador');
        } elseif ($isEmpresa) {
            $empresa = $user->empresas()
                ->where('empresa_usuario.estado', 'activo')
                ->first();
            abort_if($empresa === null, 403, 'No perteneces a una empresa activa.');
            $query->where('estado', '!=', 'borrador')
                ->whereHas('programa', fn ($programa) => $programa->where('empresa_id', $empresa->id));
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
            ->when(! $isAdmin && ! $isGestion && ! $isModerador && ! $isEmpresa, function ($q) {
                $q->where('estado', 'activo')->where('es_publico', true);
            })
            ->when($isEmpresa, function ($q) use ($user) {
                $empresa = $user->empresas()->where('empresa_usuario.estado', 'activo')->first();
                $q->where('empresa_id', $empresa?->id);
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
        $this->asegurarAlcanceModerador($reporte);
        Gate::authorize('abac', [
            AccionesAbac::ReporteVer,
            $reporte,
            $this->empresaContexto(),
        ]);

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

        // Permisos de triaje por ABAC
        $puedeAsignar = Gate::allows('abac', [AccionesAbac::ReporteAsignar, $reporte]);
        $puedeValidar = Gate::allows('abac', [AccionesAbac::ReporteValidar, $reporte]);
        $puedeRechazar = Gate::allows('abac', [AccionesAbac::ReporteRechazar, $reporte]);
        $puedeMarcarDuplicado = Gate::allows('abac', [AccionesAbac::ReporteMarcarDuplicado, $reporte]);
        $puedePagar = Gate::allows('abac', [AccionesAbac::ReportePagar, $reporte]);
        $puedeCerrar = Gate::allows('abac', [AccionesAbac::ReporteCerrar, $reporte]);

        $usuariosGestion = [];
        if ($puedeAsignar) {
            $usuariosGestion = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['gestion', 'administrador']))
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->toArray();
        }

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
            'puedeTriar' => $puedeAsignar || $puedeValidar || $puedeRechazar || $puedeMarcarDuplicado || $puedePagar || $puedeCerrar,
            'accionesDisponibles' => [
                'asignar' => $puedeAsignar,
                'validar' => $puedeValidar,
                'rechazar' => $puedeRechazar,
                'marcar_duplicado' => $puedeMarcarDuplicado,
                'pagar' => $puedePagar,
                'cerrar' => $puedeCerrar,
            ],
            'usuariosGestion' => $usuariosGestion,
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $user = $request->user();

        $programas = Programa::where('estado', 'activo')
            ->where('es_publico', true)
            ->where('reputacion_minima', '<=', (int) ($user->reputation_score ?? 0))
            ->orderBy('nombre')
            ->get();

        $programaInicial = null;
        if ($request->filled('programa')) {
            $programaInicial = $programas->firstWhere('id', (int) $request->input('programa'));
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

    // ------------------------------------------------------------------
    // Acciones de triaje (Slice 5.4)
    // ------------------------------------------------------------------

    private const TRANSICIONES_VALIDAS = [
        'enviado' => ['en_revision', 'validado', 'rechazado', 'duplicado', 'fuera_de_alcance'],
        'en_revision' => ['validado', 'rechazado', 'duplicado', 'fuera_de_alcance'],
        'validado' => ['en_reparacion', 'rechazado', 'duplicado'],
        'en_reparacion' => ['pago_pendiente', 'rechazado', 'cerrado'],
        'pago_pendiente' => ['pagado', 'rechazado', 'cerrado'],
        'pagado' => ['cerrado'],
    ];

    private function validarTransicion(Reporte $reporte, string $estadoDestino): void
    {
        $estadoActual = $reporte->estado->value;
        $permitidos = self::TRANSICIONES_VALIDAS[$estadoActual] ?? [];

        abort_if(
            ! in_array($estadoDestino, $permitidos),
            422,
            "No se puede transitar de \"{$estadoActual}\" a \"{$estadoDestino}\"."
        );
    }

    public function asignar(Reporte $reporte, Request $request): RedirectResponse
    {
        $this->asegurarAlcanceModerador($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteAsignar, $reporte]);

        $request->validate([
            'asignado_a' => ['required', 'integer', 'exists:users,id'],
        ]);

        $userId = $request->input('asignado_a');

        $reporte->update(['asignado_a' => $userId]);

        $actor = $request->user();
        $asignado = User::find($userId);
        abort_unless($asignado instanceof User, 404, 'Usuario no encontrado.');

        $reporte->eventos()->create([
            'actor_id' => $actor->id,
            'tipo' => 'asignacion',
            'nota' => "Reporte asignado a {$asignado->name}.",
            'datos' => ['asignado_a' => $userId],
        ]);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte asignado exitosamente.');
    }

    public function validar(Reporte $reporte): RedirectResponse
    {
        $this->asegurarAlcanceModerador($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteValidar, $reporte]);

        $this->validarTransicion($reporte, 'validado');
        $estadoAnterior = $reporte->estado->value;
        $reporte->update(['estado' => 'validado']);

        $reporte->eventos()->create([
            'actor_id' => request()->user()->id,
            'tipo' => 'cambio_estado',
            'nota' => 'Reporte validado.',
            'datos' => ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => 'validado'],
        ]);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte validado exitosamente.');
    }

    public function rechazar(TransitionReporteRequest $request, Reporte $reporte, ReputationService $reputacion): RedirectResponse
    {
        $this->asegurarAlcanceModerador($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteRechazar, $reporte]);

        $validated = $request->validated();
        $this->validarTransicion($reporte, 'rechazado');
        $estadoAnterior = $reporte->estado->value;

        $reporte->update(['estado' => 'rechazado']);

        if ($validated['sancionar'] ?? false) {
            $gravedad = GravedadSancion::from($validated['gravedad_sancion'] ?? GravedadSancion::Leve->value);
            $sancion = $reputacion->aplicarSancion(
                $reporte->investigador,
                $validated['nota'] ?? 'Reporte falso o fabricado durante el triaje.',
                $gravedad,
                $reporte,
                metadata: ['origen' => 'triaje', 'actor_id' => $request->user()->id],
            );
            if (config('mail.enabled')) {
                Mail::to($reporte->investigador->email)->send(new SancionAplicadaMail($sancion));
            }
        }

        $reporte->eventos()->create([
            'actor_id' => $request->user()->id,
            'tipo' => 'cambio_estado',
            'nota' => $validated['nota'] ?? 'Reporte rechazado.',
            'datos' => ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => 'rechazado'],
        ]);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte rechazado.');
    }

    public function marcarDuplicado(TransitionReporteRequest $request, Reporte $reporte): RedirectResponse
    {
        $this->asegurarAlcanceModerador($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteMarcarDuplicado, $reporte]);

        $validated = $request->validated();

        abort_if(empty($validated['reporte_duplicado_id']), 422, 'Debe especificar el reporte original.');

        $original = Reporte::find($validated['reporte_duplicado_id']);
        abort_unless($original instanceof Reporte, 404, 'El reporte original no existe.');
        abort_if($original->id === $reporte->id, 422, 'Un reporte no puede ser duplicado de sí mismo.');

        $this->validarTransicion($reporte, 'duplicado');
        $reporte->update([
            'estado' => 'duplicado',
            'es_duplicado_de' => $original->id,
        ]);

        $reporte->eventos()->create([
            'actor_id' => $request->user()->id,
            'tipo' => 'marcado_duplicado',
            'nota' => $validated['nota'] ?? "Marcado como duplicado de {$original->numero_reporte}.",
            'datos' => [
                'reporte_original_id' => $original->id,
                'numero_reporte_original' => $original->numero_reporte,
            ],
        ]);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte marcado como duplicado.');
    }

    public function pagar(TransitionReporteRequest $request, Reporte $reporte): RedirectResponse
    {
        $this->asegurarAlcanceModerador($reporte);
        Gate::authorize('abac', [AccionesAbac::ReportePagar, $reporte]);

        $validated = $request->validated();

        abort_if(empty($validated['recompensa']), 422, 'Debe especificar la recompensa.');

        $this->validarTransicion($reporte, 'pagado');
        $reporte->update([
            'estado' => 'pagado',
            'recompensa' => $validated['recompensa'],
        ]);

        $reporte->eventos()->create([
            'actor_id' => $request->user()->id,
            'tipo' => 'pago',
            'nota' => $validated['nota'] ?? "Recompensa de {$validated['recompensa']} {$reporte->moneda} pagada.",
            'datos' => ['recompensa' => $validated['recompensa'], 'moneda' => $reporte->moneda],
        ]);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Recompensa registrada exitosamente.');
    }

    public function cerrar(Reporte $reporte): RedirectResponse
    {
        $this->asegurarAlcanceModerador($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteCerrar, $reporte]);

        $this->validarTransicion($reporte, 'cerrado');
        $estadoAnterior = $reporte->estado->value;
        $reporte->update([
            'estado' => 'cerrado',
            'cerrado_en' => now(),
        ]);

        $reporte->eventos()->create([
            'actor_id' => request()->user()->id,
            'tipo' => 'cambio_estado',
            'nota' => 'Reporte cerrado.',
            'datos' => ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => 'cerrado'],
        ]);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte cerrado exitosamente.');
    }

    public function comentar(Reporte $reporte, Request $request): RedirectResponse
    {
        $this->asegurarAlcanceModerador($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteVer, $reporte]);

        $request->validate([
            'nota' => ['required', 'string', 'max:2000'],
        ]);

        $reporte->eventos()->create([
            'actor_id' => $request->user()->id,
            'tipo' => 'comentario',
            'nota' => $request->input('nota'),
        ]);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Comentario agregado.');
    }

    private function asegurarAlcanceModerador(Reporte $reporte): void
    {
        $user = request()->user();
        if (! $user?->roles()->where('slug', 'moderador')->exists()) {
            return;
        }

    }

    /** @return array{empresa_id?: int} */
    private function empresaContexto(): array
    {
        $user = request()->user();
        $empresa = $user?->empresas()
            ->where('empresa_usuario.estado', 'activo')
            ->first();

        return $empresa === null ? [] : ['empresa_id' => $empresa->id];
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

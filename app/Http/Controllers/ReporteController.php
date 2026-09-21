<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Enums\EstadoReporte;
use App\Enums\GravedadSancion;
use App\Http\Requests\StoreReporteRequest;
use App\Http\Requests\TransitionReporteRequest;
use App\Http\Requests\UpdateReporteRequest;
use App\Mail\SancionAplicadaMail;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Pgp\Exceptions\PgpException;
use App\Services\Pgp\PgpService;
use App\Services\Reportes\LimiteDeEnvios;
use App\Services\Reputacion\Rangos;
use App\Services\Reputacion\ReputationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReporteController extends Controller
{
    private const MENSAJE_CIFRADO_NO_DISPONIBLE = 'No se pudo cifrar el reporte porque el cifrado de la plataforma no está disponible en este momento. No se guardó nada sin cifrar: inténtalo de nuevo en unos minutos; tu texto sigue en pantalla.';

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $user->load('roles');

        $roles = $user->roles->pluck('slug')->toArray();
        $isAdmin = in_array('administrador', $roles);
        $isModerador = in_array('moderador', $roles);
        $isEmpresa = in_array('empresa', $roles);

        $query = Reporte::query()
            ->with(['programa', 'investigador', 'asignadoA']);

        $idsModerados = $isModerador ? $user->idsProgramasModerados() : [];

        if ($isAdmin) {
            // El administrador ve los informes enviados de todos los programas (los borradores son de su autor).
            $query->where(fn ($scope) => $scope->where('estado', '!=', 'borrador')->orWhere('investigador_id', $user->id));
        } else {
            $query->where(function ($scope) use ($user, $idsModerados) {
                $scope->where('investigador_id', $user->id)
                    ->orWhere(function (Builder $empresa) use ($user) {
                        $empresa->where('estado', '!=', 'borrador')
                            ->whereHas('programa.empresa.usuarios', function ($usuarios) use ($user) {
                                $usuarios->whereKey($user->id)
                                    ->where('empresa_usuario.estado', 'activo')
                                    ->where('empresa_usuario.rol_interno', 'propietario');
                            });
                    });

                // Un moderador ve los informes enviados de los programas que modera.
                if ($idsModerados !== []) {
                    $scope->orWhere(fn (Builder $moderados) => $moderados->where('estado', '!=', 'borrador')->whereIn('programa_id', $idsModerados));
                }
            });
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
            ->when(! $isAdmin && ! $isModerador && ! $isEmpresa, function ($q) {
                $q->where('estado', 'activo')->where('es_publico', true);
            })
            ->when($isModerador && ! $isAdmin, function ($q) use ($idsModerados) {
                $q->where(fn ($alcance) => $alcance->whereIn('id', $idsModerados)->orWhere(fn ($abiertos) => $abiertos->where('estado', 'activo')->where('es_publico', true)));
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
        $reporte->load('programa.empresa');
        abort_unless($this->puedeVerContenido($reporte), 403, 'No tienes permiso para ver este reporte.');
        Gate::authorize('abac', [
            AccionesAbac::ReporteVer,
            $reporte,
            $this->empresaContexto(),
        ]);

        $puedeVerNotasInternas = Gate::allows('abac', [AccionesAbac::ReporteVerNotasInternas, $reporte]);

        $reporte->load([
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

        // El contenido confidencial se descifra con la clave privada de la
        // plataforma y nunca se expone el ciphertext al frontend.
        $contenido = $this->contenidoDescifrado($reporte);
        $cifradoIndisponible = $contenido['indisponible'];
        $claveHuella = $contenido['clave_huella'];
        $descripcion = $contenido['descripcion'];
        $poc = $contenido['poc'];

        $reporteArray = $reporte->toArray();
        $reporteArray['descripcion'] = $descripcion;
        $reporteArray['poc'] = $poc;

        // Permisos de triaje por ABAC
        $puedeAsignar = Gate::allows('abac', [AccionesAbac::ReporteAsignar, $reporte]);
        $puedeValidar = Gate::allows('abac', [AccionesAbac::ReporteValidar, $reporte]) && $this->transicionPosible($reporte, 'validado');
        $puedeRevisar = Gate::allows('abac', [AccionesAbac::ReporteValidar, $reporte]) && $this->transicionPosible($reporte, 'en_revision');
        $puedeRechazar = Gate::allows('abac', [AccionesAbac::ReporteRechazar, $reporte]) && $this->transicionPosible($reporte, 'rechazado');
        $puedeMarcarDuplicado = Gate::allows('abac', [AccionesAbac::ReporteMarcarDuplicado, $reporte]) && $this->transicionPosible($reporte, 'duplicado');
        // Marcar en reparación y cerrar corresponden a la empresa dueña del programa (y al admin),
        // por eso se evalúan con el contexto de la empresa.
        $puedeMarcarEnReparacion = Gate::allows('abac', [AccionesAbac::ReporteMarcarEnReparacion, $reporte, $this->empresaContexto()]) && $this->transicionPosible($reporte, 'en_reparacion');
        $puedeCerrar = Gate::allows('abac', [AccionesAbac::ReporteCerrar, $reporte, $this->empresaContexto()]) && $this->transicionPosible($reporte, 'cerrado');

        // Originales posibles para marcar un duplicado: otros informes del mismo programa.
        $candidatosDuplicado = $puedeMarcarDuplicado
            ? Reporte::query()
                ->where('programa_id', $reporte->programa_id)
                ->where('id', '!=', $reporte->id)
                ->where('estado', '!=', 'borrador')
                ->orderBy('id')
                ->limit(100)
                ->get(['id', 'numero_reporte', 'titulo', 'estado'])
                ->map(fn (Reporte $candidato) => [
                    'id' => $candidato->id,
                    'numero_reporte' => $candidato->numero_reporte,
                    'titulo' => $candidato->titulo,
                    'estado' => $candidato->estado->value,
                ])
                ->all()
            : [];

        $moderadoresAsignables = [];
        if ($puedeAsignar) {
            // Solo pueden revisar el informe los moderadores de su programa (y no su propio autor).
            $moderadoresAsignables = User::whereHas('roles', fn ($q) => $q->where('slug', 'moderador'))
                ->whereHas('programasModerados', fn ($q) => $q->whereKey($reporte->programa_id))
                ->whereKeyNot($reporte->investigador_id)
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->toArray();
        }

        $puedeModerar = request()->user()->puedeModerarPrograma($reporte->programa_id);

        // Historial del autor: ayuda a valorar cuánto confiar en el informe.
        $historialInvestigador = $puedeModerar ? $this->historialInvestigador($reporte->investigador) : null;

        return Inertia::render('reportes/Show', [
            'historialInvestigador' => $historialInvestigador,
            'reporte' => [
                ...$reporteArray,
                'programa' => [
                    ...$reporte->programa->only(['id', 'nombre', 'slug', 'poc_schema']),
                    'estado' => $reporte->programa->estado->value,
                    'empresa_nombre' => $reporte->programa->empresa === null
                        ? null
                        : ($reporte->programa->empresa->nombre_comercial ?? $reporte->programa->empresa->razon_social),
                    // El alcance solo le hace falta a quien revisa: comprueba que el hallazgo esté en él.
                    ...($puedeModerar ? [
                        'bugs_buscados' => $reporte->programa->bugs_buscados,
                        'objetivos' => $reporte->programa->objetivos()->get(['id', 'tipo', 'valor', 'descripcion'])->all(),
                    ] : []),
                ],
                'investigador' => $reporte->investigador->only(['id', 'name']),
                'asignadoA' => $reporte->asignadoA?->only(['id', 'name']),
                'duplicadoDe' => $reporte->duplicadoDe?->only(['id', 'numero_reporte', 'titulo']),
                'eventos' => $eventos,
            ],
            'cifradoIndisponible' => $cifradoIndisponible,
            'claveHuella' => $claveHuella,
            'puedeVerNotasInternas' => $puedeVerNotasInternas,
            'puedeModerar' => $puedeModerar,
            'candidatosDuplicado' => $candidatosDuplicado,
            'puedeTriar' => $puedeAsignar || $puedeRevisar || $puedeValidar || $puedeRechazar || $puedeMarcarDuplicado || $puedeMarcarEnReparacion || $puedeCerrar,
            'accionesDisponibles' => [
                'asignar' => $puedeAsignar,
                'revisar' => $puedeRevisar,
                'validar' => $puedeValidar,
                'rechazar' => $puedeRechazar,
                'marcar_duplicado' => $puedeMarcarDuplicado,
                'reparacion' => $puedeMarcarEnReparacion,
                'cerrar' => $puedeCerrar,
            ],
            'moderadoresAsignables' => $moderadoresAsignables,
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $user = $request->user();

        $programas = Programa::where('estado', 'activo')
            ->where('es_publico', true)
            ->whereIn('nivel_acceso', app(Rangos::class)->nivelesAccesibles((int) ($user->reputation_score ?? 0)))
            // Quien modera un programa no puede reportar en él: vería la vulnerabilidad de los demás.
            ->when($user->tieneRol('moderador'), fn ($query) => $query->whereNotIn('id', $user->idsProgramasModerados()))
            // Quien pertenece a una empresa no reporta a sus programas: conoce su interior.
            ->when($user->idEmpresaActiva(), fn ($query, $empresaId) => $query->where(fn ($programas) => $programas->whereNull('empresa_id')->orWhere('empresa_id', '!=', $empresaId)))
            ->orderBy('nombre')
            ->get();

        $programaInicial = null;
        if ($request->filled('programa')) {
            $programaInicial = $programas->firstWhere('id', (int) $request->input('programa'));
        }

        return Inertia::render('reportes/Create', [
            'programas' => $programas->map(fn ($p) => $p->only(['id', 'nombre', 'slug', 'poc_schema'])),
            'programaInicial' => $programaInicial?->only(['id', 'nombre', 'slug', 'poc_schema']),
        ]);
    }

    public function store(StoreReporteRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // "Guardar y enviar" cuenta como un envío: se frena el envío masivo antes de crear nada.
        // Guardar solo un borrador no llega al programa, así que no tiene este límite.
        if ($request->boolean('enviar')) {
            $bloqueo = app(LimiteDeEnvios::class)->motivoDeBloqueo($user, (int) $validated['programa_id'], $validated['titulo']);

            if ($bloqueo !== null) {
                return redirect()->back()
                    ->withErrors(['limite' => $bloqueo])
                    ->with('error', $bloqueo);
            }
        }

        $poc = $validated['poc'] ?? [];

        if (! is_array($poc)) {
            $poc = [];
        }

        try {
            $cifrado = app(PgpService::class)->cifrarReporte($validated['descripcion'], $poc);
        } catch (PgpException $e) {
            report($e);

            return redirect()->back()
                ->withErrors(['pgp' => self::MENSAJE_CIFRADO_NO_DISPONIBLE])
                ->withInput();
        }

        $programa = Programa::query()->where('id', (int) $validated['programa_id'])->first();
        abort_if($programa === null, 404, 'Programa no encontrado.');

        $reporte = DB::transaction(function () use ($validated, $user, $cifrado) {
            $reporte = Reporte::create([
                'numero_reporte' => $this->generarNumeroReporte(),
                'programa_id' => $validated['programa_id'],
                'investigador_id' => $user->id,
                'titulo' => $validated['titulo'],
                'descripcion' => $cifrado['descripcion'],
                'categoria' => $validated['categoria'] ?? null,
                'vector_cvss' => $validated['vector_cvss'] ?? null,
                'puntuacion_cvss' => $validated['puntuacion_cvss'] ?? null,
                'severidad' => $validated['severidad'] ?? null,
                'poc' => $cifrado['poc'],
                'estado' => 'borrador',
                'clave_huella' => $cifrado['clave_huella'],
            ]);

            $reporte->eventos()->create([
                'actor_id' => $user->id,
                'tipo' => 'creado',
                'nota' => 'Reporte creado como borrador.',
            ]);

            return $reporte;
        });

        // "Guardar y enviar": un borrador no llega a la empresa ni a los moderadores.
        if ($request->boolean('enviar')) {
            $this->marcarEnviado($reporte, $user);

            return redirect()->route('reportes.show', $reporte)
                ->with('success', 'Reporte enviado exitosamente.');
        }

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte guardado como borrador. Envíalo para que lo revisen.');
    }

    public function edit(Reporte $reporte): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteEditar, $reporte]);

        $reporte->load('programa');

        try {
            $descifrado = app(PgpService::class)->descifrarReporte(
                (string) $reporte->descripcion,
                $reporte->poc,
            );
        } catch (PgpException) {
            $descifrado = ['descripcion' => '', 'poc' => null];
        }

        $reporteArray = $reporte->toArray();
        $reporteArray['descripcion'] = $descifrado['descripcion'];
        $reporteArray['poc'] = $descifrado['poc'];

        return Inertia::render('reportes/Edit', [
            'reporte' => [
                ...$reporteArray,
                'programa' => $reporte->programa->only(['id', 'nombre', 'slug', 'poc_schema']),
            ],
        ]);
    }

    public function update(UpdateReporteRequest $request, Reporte $reporte): RedirectResponse
    {
        $validated = $request->validated();

        $pgpService = app(PgpService::class);

        // Se descifra el estado actual para no re-cifrar un bloque ya cifrado.
        $actual = $pgpService->descifrarReporte((string) $reporte->descripcion, $reporte->poc);

        $descripcion = $validated['descripcion'] ?? $actual['descripcion'];
        $poc = $validated['poc'] ?? $actual['poc'] ?? [];

        if (! is_array($poc)) {
            $poc = [];
        }

        try {
            $cifrado = $pgpService->cifrarReporte($descripcion, $poc);
        } catch (PgpException $e) {
            report($e);

            return redirect()->back()
                ->withErrors(['pgp' => self::MENSAJE_CIFRADO_NO_DISPONIBLE])
                ->withInput();
        }

        $reporte->update([
            ...$validated,
            'descripcion' => $cifrado['descripcion'],
            'poc' => $cifrado['poc'],
            'clave_huella' => $cifrado['clave_huella'],
        ]);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte actualizado exitosamente.');
    }

    public function enviar(Reporte $reporte): RedirectResponse
    {
        Gate::authorize('abac', [AccionesAbac::ReporteEnviar, $reporte]);

        $bloqueo = app(LimiteDeEnvios::class)->motivoDeBloqueo(request()->user(), $reporte->programa_id, $reporte->titulo, $reporte->id);

        if ($bloqueo !== null) {
            return redirect()->route('reportes.show', $reporte)->with('error', $bloqueo);
        }

        $this->marcarEnviado($reporte, request()->user());

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte enviado exitosamente.');
    }

    private function marcarEnviado(Reporte $reporte, User $autor): void
    {
        $reporte->update([
            'estado' => 'enviado',
            'enviado_en' => now(),
        ]);

        $reporte->eventos()->create([
            'actor_id' => $autor->id,
            'tipo' => 'enviado',
            'nota' => 'Reporte enviado para revision.',
        ]);
    }

    // ------------------------------------------------------------------
    // Acciones de triaje (Slice 5.4)
    // ------------------------------------------------------------------

    private const TRANSICIONES_VALIDAS = [
        'enviado' => ['en_revision', 'validado', 'rechazado', 'duplicado', 'fuera_de_alcance'],
        'en_revision' => ['validado', 'rechazado', 'duplicado', 'fuera_de_alcance'],
        // Tras validar, la empresa puede marcar el informe en reparación o cerrarlo directamente
        // como resuelto (sin pasar por el estado intermedio).
        'validado' => ['en_reparacion', 'cerrado', 'rechazado', 'duplicado'],
        'en_reparacion' => ['cerrado', 'rechazado'],
    ];

    private function transicionPosible(Reporte $reporte, string $estadoDestino): bool
    {
        return in_array($estadoDestino, self::TRANSICIONES_VALIDAS[$reporte->estado->value] ?? [], true);
    }

    private function validarTransicion(Reporte $reporte, string $estadoDestino): void
    {
        $estadoActual = $reporte->estado->value;
        $permitidos = self::TRANSICIONES_VALIDAS[$estadoActual] ?? [];

        // Un error de validación (y no un abort) permite mostrar el mensaje en la
        // misma página cuando otro revisor cambió el estado mientras se tenía abierta.
        if (! in_array($estadoDestino, $permitidos, true)) {
            throw ValidationException::withMessages([
                'estado' => "El informe ya no puede pasar de \"{$estadoActual}\" a \"{$estadoDestino}\". Recarga la página para ver su estado actual.",
            ]);
        }
    }

    /**
     * Contenido del informe en JSON, para leerlo dentro de una lista sin abrir su página.
     */
    public function vistaRapida(Reporte $reporte): JsonResponse
    {
        $reporte->load('programa.empresa');
        $this->asegurarAcceso($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteVer, $reporte, $this->empresaContexto()]);

        $contenido = $this->contenidoDescifrado($reporte);

        return response()->json([
            'id' => $reporte->id,
            'descripcion' => $contenido['descripcion'],
            'poc' => $contenido['poc'],
            'poc_schema' => $reporte->programa->poc_schema,
            'cifrado_indisponible' => $contenido['indisponible'],
            'categoria' => $reporte->categoria,
            'vector_cvss' => $reporte->vector_cvss,
            'puntuacion_cvss' => $reporte->puntuacion_cvss,
            'puede_revisar' => Gate::allows('abac', [AccionesAbac::ReporteValidar, $reporte, $this->empresaContexto()])
                && $this->transicionPosible($reporte, 'en_revision'),
        ]);
    }

    /**
     * @return array{descripcion: string|null, poc: array<int|string, mixed>|null, clave_huella: string|null, indisponible: bool}
     */
    private function contenidoDescifrado(Reporte $reporte): array
    {
        try {
            $descifrado = app(PgpService::class)->descifrarReporte((string) $reporte->descripcion, $reporte->poc);

            return [
                'descripcion' => $descifrado['descripcion'],
                'poc' => $descifrado['poc'],
                'clave_huella' => $descifrado['clave_huella'],
                'indisponible' => false,
            ];
        } catch (PgpException $e) {
            report($e);

            return ['descripcion' => null, 'poc' => null, 'clave_huella' => $reporte->clave_huella, 'indisponible' => true];
        }
    }

    /**
     * @return array{reputation_score: int, informes: int, aprobados: int, descartados: int}
     */
    private function historialInvestigador(User $investigador): array
    {
        $enviados = fn () => Reporte::query()->where('investigador_id', $investigador->id)->where('estado', '!=', 'borrador');

        return [
            'reputation_score' => $investigador->reputation_score,
            'informes' => $enviados()->count(),
            'aprobados' => $enviados()->whereIn('estado', Reporte::ESTADOS_APROBADOS)->count(),
            'descartados' => $enviados()->whereIn('estado', Reporte::ESTADOS_RECHAZADOS)->count(),
        ];
    }

    /**
     * El revisor toma el informe: pasa a "en revisión" y queda asignado a él
     * (si nadie lo tenía), lo que se refleja en la línea de tiempo del investigador.
     */
    public function revisar(Reporte $reporte, Request $request): RedirectResponse
    {
        $this->asegurarAcceso($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteValidar, $reporte]);

        $this->validarTransicion($reporte, 'en_revision');
        $revisor = $request->user();
        $estadoAnterior = $reporte->estado->value;

        $reporte->update([
            'estado' => 'en_revision',
            'asignado_a' => $reporte->asignado_a ?? $revisor->id,
        ]);

        $reporte->eventos()->create([
            'actor_id' => $revisor->id,
            'tipo' => 'cambio_estado',
            'nota' => 'Un moderador comenzó a revisar tu informe.',
            'datos' => ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => 'en_revision'],
        ]);

        return redirect()->back(fallback: route('reportes.show', $reporte))
            ->with('success', 'Revisión iniciada.');
    }

    public function asignar(Reporte $reporte, Request $request): RedirectResponse
    {
        $this->asegurarAcceso($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteAsignar, $reporte]);

        $request->validate([
            'asignado_a' => ['required', 'integer', 'exists:users,id'],
        ]);

        $userId = $request->input('asignado_a');

        $actor = $request->user();
        $asignado = User::find($userId);
        abort_unless($asignado instanceof User, 404, 'Usuario no encontrado.');

        if (! $asignado->puedeModerarPrograma($reporte->programa_id) || (int) $asignado->id === (int) $reporte->investigador_id) {
            throw ValidationException::withMessages(['asignado_a' => 'Ese usuario no puede revisar este informe: debe moderar el programa y no ser su autor.']);
        }

        $reporte->update(['asignado_a' => $userId]);

        $reporte->eventos()->create([
            'actor_id' => $actor->id,
            'tipo' => 'asignacion',
            'nota' => "Reporte asignado a {$asignado->name}.",
            'datos' => ['asignado_a' => $userId],
        ]);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte asignado exitosamente.');
    }

    public function validar(Reporte $reporte, ReputationService $reputacion): RedirectResponse
    {
        $this->asegurarAcceso($reporte);
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

        $reputacion->otorgarPuntosEvento($reporte->investigador_id, 'reporte_validado', $reporte);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte validado exitosamente.');
    }

    public function rechazar(TransitionReporteRequest $request, Reporte $reporte, ReputationService $reputacion): RedirectResponse
    {
        $this->asegurarAcceso($reporte);
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
                aplicadaPor: $request->user(),
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
        $this->asegurarAcceso($reporte);
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

    public function reparacion(Reporte $reporte): RedirectResponse
    {
        $this->asegurarAcceso($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteMarcarEnReparacion, $reporte, $this->empresaContexto()]);

        $this->validarTransicion($reporte, 'en_reparacion');
        $estadoAnterior = $reporte->estado->value;
        $reporte->update(['estado' => 'en_reparacion']);

        $reporte->eventos()->create([
            'actor_id' => request()->user()->id,
            'tipo' => 'cambio_estado',
            'nota' => 'La empresa está corrigiendo la vulnerabilidad.',
            'datos' => ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => 'en_reparacion'],
        ]);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Informe marcado en reparación.');
    }

    public function cerrar(Reporte $reporte, ReputationService $reputacion): RedirectResponse
    {
        $this->asegurarAcceso($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteCerrar, $reporte, $this->empresaContexto()]);

        $this->validarTransicion($reporte, 'cerrado');
        $estadoAnterior = $reporte->estado->value;
        $reporte->update([
            'estado' => 'cerrado',
            'cerrado_en' => now(),
        ]);

        $reporte->eventos()->create([
            'actor_id' => request()->user()->id,
            'tipo' => 'cambio_estado',
            'nota' => 'Vulnerabilidad resuelta: informe cerrado.',
            'datos' => ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => 'cerrado'],
        ]);

        // La recompensa por un informe válido es la reputación: se otorga al resolverlo.
        $reputacion->otorgarPuntosEvento($reporte->investigador_id, 'reporte_resuelto', $reporte);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Informe cerrado como resuelto. El investigador recibió sus puntos de reputación.');
    }

    public function comentar(Reporte $reporte, Request $request): RedirectResponse
    {
        $this->asegurarAcceso($reporte);
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

    /**
     * Solo quien puede ver el contenido del reporte puede actuar sobre él.
     */
    private function asegurarAcceso(Reporte $reporte): void
    {
        $reporte->loadMissing('programa.empresa');

        abort_unless($this->puedeVerContenido($reporte), 403, 'No tienes permiso para acceder a este reporte.');
    }

    private function puedeVerContenido(Reporte $reporte): bool
    {
        $user = request()->user();

        if ($user === null) {
            return false;
        }

        if ($user->tieneRol('administrador')) {
            return true;
        }

        if ((int) $reporte->investigador_id === (int) $user->id) {
            return true;
        }

        // Los borradores son solo del investigador: ni la empresa ni los moderadores los ven.
        if ($reporte->estado === EstadoReporte::Borrador) {
            return false;
        }

        // Un moderador accede a los informes de los programas que modera.
        if ($user->puedeModerarPrograma($reporte->programa_id)) {
            return true;
        }

        // Los informes de una empresa solo los ve su propietario (no los publicadores).
        return $reporte->programa->empresa?->usuarios()
            ->whereKey($user->id)
            ->where('empresa_usuario.estado', 'activo')
            ->where('empresa_usuario.rol_interno', 'propietario')
            ->exists() ?? false;
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

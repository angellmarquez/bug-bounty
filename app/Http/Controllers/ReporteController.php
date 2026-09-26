<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Enums\EstadoReporte;
use App\Enums\GravedadSancion;
use App\Http\Requests\StoreReporteRequest;
use App\Http\Requests\TransitionReporteRequest;
use App\Http\Requests\UpdateReporteRequest;
use App\Mail\SancionAplicadaMail;
use App\Models\Auditoria;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Rules\PocCumpleSchema;
use App\Services\Pgp\Exceptions\PgpException;
use App\Services\Pgp\PgpService;
use App\Services\Reportes\ColaDeValidacion;
use App\Services\Reportes\LimiteDeEnvios;
use App\Services\Reputacion\Rangos;
use App\Services\Reputacion\ReputationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
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
                        $empresa->whereIn('estado', Reporte::ESTADOS_VISIBLES_EMPRESA)
                            ->whereHas('programa.empresa.usuarios', function ($usuarios) use ($user) {
                                $usuarios->whereKey($user->id)
                                    ->where('empresa_usuario.estado', 'activo')
                                    ->where('empresa_usuario.rol_interno', 'propietario');
                            });
                    });

                // Un moderador ve los informes que tomó y el siguiente de la cola de cada programa
                // que modera (primero en llegar, primero en revisarse); el resto espera su turno.
                if ($idsModerados !== []) {
                    $siguientes = collect($idsModerados)->map(fn (int $id) => Reporte::siguienteEnCola($id)?->id)->filter()->all();
                    $scope->orWhere(fn (Builder $moderados) => $moderados->where('estado', '!=', 'borrador')
                        ->whereIn('programa_id', $idsModerados)
                        ->where(fn (Builder $suyos) => $suyos->where('asignado_a', $user->id)->orWhereIn('id', $siguientes)));
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

        $programasCiegos = Reporte::programasEnTriajeCiego($user);
        $reportes = $query->latest()->paginate(15)->withQueryString()
            // Del autor solo lo que muestra la lista, y anónimo para quien lo modera (triaje ciego).
            ->through(fn (Reporte $reporte): array => [
                ...$reporte->attributesToArray(),
                'investigador_id' => $reporte->ocultaAutorA($user, $programasCiegos) ? 0 : $reporte->investigador_id,
                'programa' => $reporte->programa->only(['id', 'nombre']),
                'investigador' => $reporte->autorPara($user, $programasCiegos),
                'asignadoA' => $reporte->asignadoA?->only(['id', 'name']),
            ]);

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
            'puedeCrear' => $this->puedeReportar($user),
        ]);
    }

    /**
     * Separación de funciones: solo un investigador activo envía informes (nunca el admin,
     * un moderador ni una empresa). En qué programa puede hacerlo lo decide ABAC programa a programa.
     */
    private function puedeReportar(User $user): bool
    {
        return ($user->is_active ?? true) && $user->tieneRol('investigador');
    }

    public function show(Reporte $reporte): InertiaResponse
    {
        $reporte->load('programa.empresa');
        abort_unless($this->puedeVerContenido($reporte), 403, $this->motivoSinAcceso($reporte));
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

        $user = request()->user();
        $puedeModerar = $user?->puedeModerarPrograma($reporte->programa_id) ?? false;

        // Capa 3: Triaje Ciego (Blind Triage)
        // Quien modera el reporte (no siendo el autor ni administrador) no ve la identidad
        // del investigador, pero sí su rango (reputación) y métricas de historial.
        $esCiego = $reporte->ocultaAutorA($user);

        $eventos = $reporte->eventos->map(function ($evento) use ($esCiego, $reporte) {
            $esDelAutor = (int) $evento->actor_id === (int) $reporte->investigador_id;
            $actorData = $esCiego && $esDelAutor
                ? ['id' => 0, 'name' => Reporte::AUTOR_ANONIMO]
                : $evento->actor?->only(['id', 'name']);

            return [
                'id' => $evento->id,
                'reporte_id' => $evento->reporte_id,
                'tipo' => $evento->tipo->value,
                'actor_id' => $esCiego && $esDelAutor ? 0 : $evento->actor_id,
                'descripcion' => $evento->nota,
                'metadata' => $evento->datos,
                'created_at' => $evento->created_at?->toISOString(),
                'actor' => $actorData,
            ];
        });

        $puedeDescifrarPoc = Gate::allows('abac', [
            AccionesAbac::ReporteDecryptPoc,
            $reporte,
            $this->empresaContexto(),
        ]);

        $contenido = $this->contenidoParaLector($reporte, $puedeDescifrarPoc);
        $cifradoIndisponible = $contenido['indisponible'];
        $claveHuella = $contenido['clave_huella'];
        $descripcion = $contenido['descripcion'];
        $poc = $contenido['poc'];

        // Solo se serializan columnas: las relaciones se envían aparte, ya filtradas (el
        // investigador completo llevaba su email y datos de cuenta al navegador).
        $reporteArray = $reporte->attributesToArray();
        $reporteArray['descripcion'] = $descripcion;
        $reporteArray['poc'] = $poc;
        if ($esCiego) {
            $reporteArray['investigador_id'] = 0;
        }

        // Permisos de triaje por ABAC
        $puedeAsignar = Gate::allows('abac', [AccionesAbac::ReporteAsignar, $reporte]);
        $puedeValidar = Gate::allows('abac', [AccionesAbac::ReporteValidar, $reporte]) && $this->transicionPosible($reporte, 'validado');
        $puedeRevisar = Gate::allows('abac', [AccionesAbac::ReporteRevisar, $reporte]) && $this->transicionPosible($reporte, 'en_revision');
        $puedePedirInfo = Gate::allows('abac', [AccionesAbac::ReporteValidar, $reporte]) && $this->transicionPosible($reporte, 'needs_info');
        $puedeRechazar = Gate::allows('abac', [AccionesAbac::ReporteRechazar, $reporte]) && $this->transicionPosible($reporte, 'rechazado');
        $puedeMarcarDuplicado = Gate::allows('abac', [AccionesAbac::ReporteMarcarDuplicado, $reporte]) && $this->transicionPosible($reporte, 'duplicado');
        // Marcar en reparación y cerrar corresponden a la empresa dueña del programa (y al admin),
        // por eso se evalúan con el contexto de la empresa.
        $puedeMarcarEnReparacion = Gate::allows('abac', [AccionesAbac::ReporteMarcarEnReparacion, $reporte, $this->empresaContexto()]) && $this->transicionPosible($reporte, 'en_reparacion');
        $puedeCerrar = Gate::allows('abac', [AccionesAbac::ReporteCerrar, $reporte, $this->empresaContexto()]) && $this->transicionPosible($reporte, 'cerrado');

        // Orden de llegada: la misma regla que aplican validar(), reparacion() y cerrar(), para no
        // ofrecer un botón que el servidor rechazaría. Se explica por qué hay que esperar.
        $esperaTurno = null;
        $cola = app(ColaDeValidacion::class);
        if ($puedeValidar && ($anterior = $cola->anteriorPendiente($reporte, ColaDeValidacion::PENDIENTES_DE_TRIAJE)) !== null) {
            $puedeValidar = false;
            $esperaTurno = $this->mensajeDeTurno($anterior);
        }
        if (($puedeMarcarEnReparacion || $puedeCerrar) && $reporte->estado === EstadoReporte::Validado
            && ($anterior = $cola->anteriorPendiente($reporte, ColaDeValidacion::PENDIENTES_DE_CONFIRMAR)) !== null) {
            $puedeMarcarEnReparacion = false;
            $puedeCerrar = false;
            $esperaTurno = $this->mensajeDeTurno($anterior);
        }

        // Capa 2: Candidatos a duplicado restringidos a reportes anteriores en el tiempo (prioridad temporal anti-robo)
        $candidatosDuplicado = $puedeMarcarDuplicado
            ? Reporte::query()
                ->where('programa_id', $reporte->programa_id)
                ->where('id', '!=', $reporte->id)
                ->where('estado', '!=', 'borrador')
                ->whereRaw('COALESCE(enviado_en, created_at) <= ?', [ColaDeValidacion::prioridad($reporte)])
                ->orderByRaw('COALESCE(enviado_en, created_at) asc')
                ->orderBy('id')
                ->limit(100)
                ->get(['id', 'numero_reporte', 'titulo', 'estado', 'enviado_en', 'created_at'])
                ->filter(fn (Reporte $candidato): bool => ColaDeValidacion::llegoAntes($candidato, $reporte))
                ->values()
                ->map(fn (Reporte $candidato) => [
                    'id' => $candidato->id,
                    'numero_reporte' => $candidato->numero_reporte,
                    'titulo' => $candidato->titulo,
                    'estado' => $candidato->estado->value,
                    'created_at' => $candidato->created_at?->toISOString(),
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

        $investigadorPayload = $reporte->autorPara($user);

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
                    // Queda cifrado en la base, así que se descifra recién acá, para quien modera de forma segura.
                    ...($puedeModerar ? $this->alcanceProgramaParaModerador($reporte->programa) : []),
                ],
                'investigador' => $investigadorPayload,
                'asignadoA' => $reporte->asignadoA?->only(['id', 'name']),
                'duplicadoDe' => $reporte->duplicadoDe?->only(['id', 'numero_reporte', 'titulo']),
                'eventos' => $eventos,
            ],
            'cifradoIndisponible' => $cifradoIndisponible,
            'claveHuella' => $claveHuella,
            'puedeVerNotasInternas' => $puedeVerNotasInternas,
            'puedeModerar' => $puedeModerar,
            'candidatosDuplicado' => $candidatosDuplicado,
            'puedeTriar' => $puedeAsignar || $puedeRevisar || $puedePedirInfo || $puedeValidar || $puedeRechazar || $puedeMarcarDuplicado || $puedeMarcarEnReparacion || $puedeCerrar,
            'accionesDisponibles' => [
                'asignar' => $puedeAsignar,
                'revisar' => $puedeRevisar,
                'pedir_info' => $puedePedirInfo,
                'validar' => $puedeValidar,
                'rechazar' => $puedeRechazar,
                'marcar_duplicado' => $puedeMarcarDuplicado,
                'reparacion' => $puedeMarcarEnReparacion,
                'cerrar' => $puedeCerrar,
            ],
            'moderadoresAsignables' => $moderadoresAsignables,
            'esperaTurno' => $esperaTurno,
            'avisoCola' => $reporte->asignado_a === null && $reporte->esSiguienteEnCola() && $puedeRevisar
                ? 'Es el siguiente informe de la cola: el más antiguo que nadie revisa todavía. Al iniciar la revisión queda asignado a ti.'
                : null,
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless($this->puedeReportar($user), 403, 'Solo los investigadores envían informes.');

        $programas = Programa::with('empresa')->where('estado', 'activo')
            ->where(function ($q) use ($user) {
                $q->where('es_publico', true)
                    ->orWhereHas('hackersInvitados', function ($qi) use ($user) {
                        $qi->where('users.id', $user->id)->where('programa_invitados.estado', 'aceptada');
                    });
            })
            ->whereIn('nivel_acceso', app(Rangos::class)->nivelesAccesibles((int) ($user->reputation_score ?? 0)))
            // Quien modera un programa no puede reportar en él: vería la vulnerabilidad de los demás.
            ->when($user->tieneRol('moderador'), fn ($query) => $query->whereNotIn('id', $user->idsProgramasModerados()))
            // Quien pertenece a una empresa no reporta a sus programas: conoce su interior.
            ->when($user->idEmpresaActiva(), fn ($query, $empresaId) => $query->where(fn ($programas) => $programas->whereNull('empresa_id')->orWhere('empresa_id', '!=', $empresaId)))
            ->orderBy('nombre')
            ->get()
            // El formulario solo ofrece los programas donde ABAC dejará guardar el informe.
            ->filter(fn (Programa $programa): bool => Gate::allows('abac', [AccionesAbac::ReporteCrear, $programa]))
            ->values();

        $programaInicial = null;
        if ($request->filled('programa')) {
            $programaInicial = $programas->firstWhere('id', (int) $request->input('programa'));
        }

        return Inertia::render('reportes/Create', [
            'programas' => $programas->map(function ($p) {
                $nombreEmpresa = $p->empresa ? ($p->empresa->nombre_comercial ?? $p->empresa->razon_social) : null;

                return [
                    'id' => $p->id,
                    'nombre' => $nombreEmpresa ? "{$p->nombre} ({$nombreEmpresa})" : $p->nombre,
                    'slug' => $p->slug,
                    'poc_schema' => $p->poc_schema,
                ];
            }),
            'programaInicial' => $programaInicial ? [
                'id' => $programaInicial->id,
                'nombre' => $programaInicial->empresa
                    ? "{$programaInicial->nombre} (".($programaInicial->empresa->nombre_comercial ?? $programaInicial->empresa->razon_social).')'
                    : $programaInicial->nombre,
                'slug' => $programaInicial->slug,
                'poc_schema' => $programaInicial->poc_schema,
            ] : null,
        ]);
    }

    public function store(StoreReporteRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $programa = Programa::query()->where('id', (int) $validated['programa_id'])->first();
        abort_if($programa === null, 404, 'Programa no encontrado.');

        Gate::authorize('abac', [AccionesAbac::ReporteCrear, $programa]);

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

        $programa = Programa::query()->with('empresa')->where('id', (int) $validated['programa_id'])->first();
        abort_if($programa === null, 404, 'Programa no encontrado.');

        try {
            $cifrado = app(PgpService::class)->cifrarReporte($validated['descripcion'], $poc, $programa->empresa);
        } catch (PgpException $e) {
            report($e);

            return redirect()->back()
                ->withErrors(['pgp' => self::MENSAJE_CIFRADO_NO_DISPONIBLE])
                ->withInput();
        }

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

            Auditoria::registrar('reportes.creado', $reporte, ['programa_id' => $reporte->programa_id], $user->id);

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
                $reporte,
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
        $reporte->loadMissing('programa.empresa');

        // Se descifra el estado actual para no re-cifrar un bloque ya cifrado.
        $actual = $pgpService->descifrarReporte((string) $reporte->descripcion, $reporte->poc, $reporte);

        $descripcion = $validated['descripcion'] ?? $actual['descripcion'];
        $poc = $validated['poc'] ?? $actual['poc'] ?? [];

        if (! is_array($poc)) {
            $poc = [];
        }

        try {
            $cifrado = $pgpService->cifrarReporte($descripcion, $poc, $reporte->programa->empresa);
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

        Auditoria::registrar('reportes.editado', $reporte, [], $request->user()->id);

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

        // Todo programa exige PoC (ver AGENTS.md): un borrador guardado sin ella
        // (o incompleta) no puede pasar a "enviado", sin importar cómo llegó así.
        $reporte->loadMissing('programa');
        $contenido = $this->contenidoDescifrado($reporte);

        if ($contenido['indisponible']) {
            return redirect()->route('reportes.show', $reporte)
                ->with('error', self::MENSAJE_CIFRADO_NO_DISPONIBLE);
        }

        $schema = $reporte->programa->poc_schema ?? [];
        $validadorPoc = Validator::make(
            ['poc' => $contenido['poc']],
            ['poc' => ['required', 'array', new PocCumpleSchema($schema)]],
            ['poc.required' => 'Agrega la prueba de concepto antes de enviar: es obligatoria en todos los programas.'],
        );

        if ($validadorPoc->fails()) {
            return redirect()->route('reportes.show', $reporte)
                ->withErrors($validadorPoc)
                ->with('error', 'No se puede enviar: falta completar la prueba de concepto.');
        }

        $this->marcarEnviado($reporte, request()->user());

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte enviado exitosamente.');
    }

    private function marcarEnviado(Reporte $reporte, User $autor): void
    {
        $reporte->update([
            'estado' => 'enviado',
            // El turno en la cola es el del primer envío: reenviar tras `needs_info` no lo pierde.
            'enviado_en' => $reporte->enviado_en ?? now(),
        ]);

        $reporte->eventos()->create([
            'actor_id' => $autor->id,
            'tipo' => 'enviado',
            'nota' => 'Reporte enviado para revision.',
        ]);

        Auditoria::registrar('reportes.enviado', $reporte, [], $autor->id);
    }

    // ------------------------------------------------------------------
    // Acciones de triaje (Slice 5.4)
    // ------------------------------------------------------------------

    private const TRANSICIONES_VALIDAS = [
        'enviado' => ['en_revision', 'needs_info', 'validado', 'rechazado', 'duplicado', 'fuera_de_alcance'],
        'en_revision' => ['needs_info', 'validado', 'rechazado', 'duplicado', 'fuera_de_alcance'],
        'needs_info' => ['en_revision', 'validado', 'rechazado', 'duplicado', 'fuera_de_alcance'],
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
    /**
     * Contenido del informe para quien lo está leyendo. La PoC solo se descifra si
     * `reportes.decrypt_poc` lo permite; entonces queda UN registro de auditoría
     * `reportes.poc_descifrado` (usuario, IP, user-agent, fecha y huella de la clave).
     * Si no, solo se descifra la descripción y la auditoría es la genérica del servicio PGP.
     *
     * @return array{descripcion: string|null, poc: array<int|string, mixed>|null, clave_huella: string|null, indisponible: bool}
     */
    private function contenidoParaLector(Reporte $reporte, bool $puedeDescifrarPoc): array
    {
        if (! $puedeDescifrarPoc) {
            try {
                $descifrado = app(PgpService::class)->descifrarReporte((string) $reporte->descripcion, null, $reporte);

                return ['descripcion' => $descifrado['descripcion'], 'poc' => null, 'clave_huella' => $descifrado['clave_huella'], 'indisponible' => false];
            } catch (PgpException $e) {
                report($e);
                Auditoria::registrar('reportes.descifrado_fallido', $reporte, ['error' => class_basename($e)]);

                return ['descripcion' => null, 'poc' => null, 'clave_huella' => $reporte->clave_huella, 'indisponible' => true];
            }
        }

        try {
            $descifrado = app(PgpService::class)->descifrarReporte((string) $reporte->descripcion, $reporte->poc, $reporte);
        } catch (PgpException $e) {
            report($e);
            // Un intento fallido también queda en Auditoría: quién quiso leer el informe y no pudo.
            Auditoria::registrar('reportes.descifrado_fallido', $reporte, ['error' => class_basename($e)]);

            return ['descripcion' => null, 'poc' => null, 'clave_huella' => $reporte->clave_huella, 'indisponible' => true];
        }

        Auditoria::registrar('reportes.poc_descifrado', $reporte, ['clave_huella' => $descifrado['clave_huella']]);

        return [
            'descripcion' => $descifrado['descripcion'],
            'poc' => $descifrado['poc'],
            'clave_huella' => $descifrado['clave_huella'],
            'indisponible' => false,
        ];
    }

    /**
     * @return array{descripcion: string|null, poc: array<int|string, mixed>|null, clave_huella: string|null, indisponible: bool}
     */
    private function contenidoDescifrado(Reporte $reporte): array
    {
        try {
            $descifrado = app(PgpService::class)->descifrarReporte((string) $reporte->descripcion, $reporte->poc, $reporte);

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
     * @return array{bugs_buscados: string|null, objetivos: array<int, array<string, mixed>>}
     */
    private function alcanceProgramaParaModerador(Programa $programa): array
    {
        $pgp = app(PgpService::class);
        $bugsBuscados = null;

        try {
            $descifrado = $pgp->descifrarPrograma($programa->descripcion, $programa->bugs_buscados, $programa);
            $bugsBuscados = $descifrado['bugs_buscados'];
        } catch (PgpException $e) {
            report($e);
            $bugsBuscados = null;
        }

        $objetivos = $programa->objetivos()->get(['id', 'tipo', 'valor', 'descripcion'])
            ->map(function ($o) use ($pgp) {
                try {
                    return [...$o->toArray(), ...$pgp->descifrarObjetivo($o->valor, $o->descripcion, $o)];
                } catch (PgpException $e) {
                    report($e);

                    return [...$o->toArray(), 'valor' => '[Contenido no disponible]', 'descripcion' => null];
                }
            })
            ->all();

        return [
            'bugs_buscados' => $bugsBuscados,
            'objetivos' => $objetivos,
        ];
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
        Gate::authorize('abac', [AccionesAbac::ReporteRevisar, $reporte]);

        $this->validarTransicion($reporte, 'en_revision');
        $revisor = $request->user();
        $estadoAnterior = $reporte->estado->value;

        // Tomarlo es atómico: si dos moderadores pulsan a la vez, solo uno se lo queda.
        $tomado = Reporte::query()
            ->whereKey($reporte->id)
            ->where('estado', $estadoAnterior)
            ->where(fn ($libre) => $libre->whereNull('asignado_a')->orWhere('asignado_a', $revisor->id))
            ->update(['estado' => 'en_revision', 'asignado_a' => $reporte->asignado_a ?? $revisor->id, 'updated_at' => now()]);

        if ($tomado === 0) {
            throw ValidationException::withMessages(['estado' => 'Otro moderador acaba de tomar este informe. Vuelve a la cola para revisar el siguiente.']);
        }

        $reporte->refresh();

        $reporte->eventos()->create([
            'actor_id' => $revisor->id,
            'tipo' => 'cambio_estado',
            'nota' => 'Un moderador comenzó a revisar tu informe.',
            'datos' => ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => 'en_revision'],
        ]);

        Auditoria::registrar('reportes.revision_iniciada', $reporte, ['estado_anterior' => $estadoAnterior], $revisor->id);

        return redirect()->back(fallback: route('reportes.show', $reporte))
            ->with('success', 'Revisión iniciada.');
    }

    /**
     * Solicitar información adicional al investigador (needs_info).
     */
    public function pedirInfo(Request $request, Reporte $reporte): RedirectResponse
    {
        $this->asegurarAcceso($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteValidar, $reporte]);

        $this->validarTransicion($reporte, 'needs_info');
        $validated = $request->validate([
            'nota' => ['required', 'string', 'max:2000'],
        ]);

        $estadoAnterior = $reporte->estado->value;
        $reporte->update(['estado' => 'needs_info']);

        $reporte->eventos()->create([
            'actor_id' => $request->user()->id,
            'tipo' => 'cambio_estado',
            'nota' => $validated['nota'],
            'datos' => ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => 'needs_info'],
        ]);

        Auditoria::registrar('reportes.needs_info', $reporte, ['estado_anterior' => $estadoAnterior], $request->user()->id);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Se ha solicitado más información al investigador.');
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

        Auditoria::registrar('reportes.asignado', $reporte, ['asignado_a' => $userId], $actor->id);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte asignado exitosamente.');
    }

    public function validar(Reporte $reporte, ColaDeValidacion $cola): RedirectResponse
    {
        $this->asegurarAcceso($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteValidar, $reporte]);

        $this->validarTransicion($reporte, 'validado');
        $this->exigirTurno($reporte, $cola, ColaDeValidacion::PENDIENTES_DE_TRIAJE);
        $estadoAnterior = $reporte->estado->value;
        $reporte->update(['estado' => 'validado']);

        $reporte->eventos()->create([
            'actor_id' => request()->user()->id,
            'tipo' => 'cambio_estado',
            'nota' => 'Reporte validado.',
            'datos' => ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => 'validado'],
        ]);

        Auditoria::registrar('reportes.validado', $reporte, ['estado_anterior' => $estadoAnterior]);

        // Los puntos no se dan aquí: los da la confirmación de la empresa (ver confirmarPorEmpresa).
        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte validado. Queda pendiente de que la empresa lo confirme.');
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

        $datosEvento = ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => 'rechazado'];
        if (! empty($validated['motivo_rechazo'])) {
            $datosEvento['motivo_rechazo'] = $validated['motivo_rechazo'];
        }

        $reporte->eventos()->create([
            'actor_id' => $request->user()->id,
            'tipo' => 'cambio_estado',
            'nota' => $validated['nota'] ?? 'Reporte rechazado.',
            'datos' => $datosEvento,
        ]);

        Auditoria::registrar('reportes.rechazado', $reporte, [
            'estado_anterior' => $estadoAnterior,
            'sancionado' => (bool) ($validated['sancionar'] ?? false),
        ], $request->user()->id);

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
        abort_unless(ColaDeValidacion::llegoAntes($original, $reporte), 422, 'Un reporte solo puede ser marcado como duplicado de otro reporte enviado con anterioridad.');

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

        Auditoria::registrar('reportes.marcado_duplicado', $reporte, ['reporte_original_id' => $original->id], $request->user()->id);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Reporte marcado como duplicado.');
    }

    public function reparacion(Reporte $reporte, ColaDeValidacion $cola, ReputationService $reputacion): RedirectResponse
    {
        $this->asegurarAcceso($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteMarcarEnReparacion, $reporte, $this->empresaContexto()]);

        $this->validarTransicion($reporte, 'en_reparacion');
        $confirma = $reporte->estado === EstadoReporte::Validado;
        if ($confirma) {
            $this->exigirTurno($reporte, $cola, ColaDeValidacion::PENDIENTES_DE_CONFIRMAR);
        }
        $estadoAnterior = $reporte->estado->value;
        $reporte->update(['estado' => 'en_reparacion']);

        $reporte->eventos()->create([
            'actor_id' => request()->user()->id,
            'tipo' => 'cambio_estado',
            'nota' => 'La empresa está corrigiendo la vulnerabilidad.',
            'datos' => ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => 'en_reparacion'],
        ]);

        Auditoria::registrar('reportes.marcado_en_reparacion', $reporte, ['estado_anterior' => $estadoAnterior]);

        if ($confirma) {
            $this->confirmarPorEmpresa($reporte, $reputacion);
        }

        return redirect()->route('reportes.show', $reporte)
            ->with('success', $confirma
                ? 'Informe confirmado y en reparación. El investigador recibió sus puntos.'
                : 'Informe marcado en reparación.');
    }

    public function cerrar(Reporte $reporte, ReputationService $reputacion, ColaDeValidacion $cola): RedirectResponse
    {
        $this->asegurarAcceso($reporte);
        Gate::authorize('abac', [AccionesAbac::ReporteCerrar, $reporte, $this->empresaContexto()]);

        $this->validarTransicion($reporte, 'cerrado');
        // Cerrar directamente desde "validado" también es la confirmación de la empresa.
        $confirma = $reporte->estado === EstadoReporte::Validado;
        if ($confirma) {
            $this->exigirTurno($reporte, $cola, ColaDeValidacion::PENDIENTES_DE_CONFIRMAR);
        }
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

        Auditoria::registrar('reportes.cerrado', $reporte, ['estado_anterior' => $estadoAnterior]);

        if ($confirma) {
            $this->confirmarPorEmpresa($reporte, $reputacion);
        }

        // La recompensa por un informe válido es la reputación: se otorga al resolverlo.
        $reputacion->otorgarPuntosEvento($reporte->investigador_id, 'reporte_resuelto', $reporte);

        return redirect()->route('reportes.show', $reporte)
            ->with('success', 'Informe cerrado como resuelto. El investigador recibió sus puntos de reputación.');
    }

    /**
     * Bloquea la acción si un informe anterior del mismo programa sigue en `$estados`:
     * la recompensa es para el primero, así que se atienden por orden de llegada.
     *
     * @param  array<int, string>  $estados
     */
    private function exigirTurno(Reporte $reporte, ColaDeValidacion $cola, array $estados): void
    {
        $anterior = $cola->anteriorPendiente($reporte, $estados);

        if ($anterior !== null) {
            throw ValidationException::withMessages(['estado' => $this->mensajeDeTurno($anterior)]);
        }
    }

    /** Solo se nombra el informe anterior si quien pregunta puede verlo (la empresa no ve los aún sin triar). */
    private function mensajeDeTurno(Reporte $anterior): string
    {
        $cual = Gate::allows('abac', [AccionesAbac::ReporteVer, $anterior, $this->empresaContexto()])
            ? "el informe {$anterior->numero_reporte}"
            : 'un informe anterior de este programa';

        return "Aún no es su turno: primero hay que resolver {$cual}, que se envió antes. "
            .'La recompensa es para quien encontró la vulnerabilidad primero, así que se atienden por orden de llegada.';
    }

    /**
     * Por qué no se entrega el informe. Al moderador se le explica la cola: solo abre el
     * siguiente sin revisor y los que ya tomó, nunca los que esperan turno ni los de otro.
     */
    private function motivoSinAcceso(Reporte $reporte): string
    {
        $user = request()->user();

        if ($user === null || $user->tieneRol('administrador') || ! $user->puedeModerarPrograma($reporte->programa_id)
            || $reporte->estado === EstadoReporte::Borrador) {
            return 'No tienes permiso para ver este reporte.';
        }

        if ($reporte->asignado_a !== null) {
            return 'Este informe lo está revisando otro moderador.';
        }

        return 'Aún no es su turno: los informes se revisan por orden de llegada. Toma el siguiente desde la cola de moderación.';
    }

    /** La empresa confirmó un informe validado: es el momento en que el investigador gana los puntos. */
    private function confirmarPorEmpresa(Reporte $reporte, ReputationService $reputacion): void
    {
        $reputacion->otorgarPuntosEvento($reporte->investigador_id, 'reporte_validado', $reporte);
        Auditoria::registrar('reportes.confirmado_por_empresa', $reporte, [], request()->user()?->id);
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

        // Un moderador accede a los informes que tomó y al siguiente de la cola de su programa.
        if ($user->puedeModerarPrograma($reporte->programa_id)) {
            return Gate::allows('abac', [AccionesAbac::ReporteVer, $reporte]);
        }

        // La empresa dueña del programa solo ve informes que ya están en revisión, solicitando info, validados, en reparación o cerrados.
        // Nunca ve 'borrador', 'enviado' (pre-triaje) ni 'rechazado'.
        if (in_array($reporte->estado->value, Reporte::ESTADOS_VISIBLES_EMPRESA, true)) {
            return $reporte->programa->empresa?->usuarios()
                ->whereKey($user->id)
                ->where('empresa_usuario.estado', 'activo')
                ->where('empresa_usuario.rol_interno', 'propietario')
                ->exists() ?? false;
        }

        return false;
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

<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Http\Requests\StoreProgramaRequest;
use App\Http\Requests\UpdateProgramaRequest;
use App\Models\Auditoria;
use App\Models\InvitacionPrograma;
use App\Models\ObjetivoPrograma;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Moderacion\ColaDeInformes;
use App\Services\Pgp\Exceptions\PgpException;
use App\Services\Pgp\PgpService;
use App\Services\Reputacion\Rangos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
        $isEmpresa = in_array('empresa', $roles);

        $query = Programa::query()->with(['creador', 'empresa', 'objetivos']);

        if ($isAdmin) {
            // Admin ve todos
        } else {
            $query->visiblesPara($user);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('busqueda')) {
            // La descripcion queda cifrada en la base: no se puede buscar por ella con LIKE.
            $query->where('nombre', 'like', '%'.$request->input('busqueda').'%');
        }

        $programas = $query->orderBy('nombre')->paginate(15)->withQueryString();

        return Inertia::render('programas/Index', [
            'programas' => $programas,
            'filtros' => $request->only(['estado', 'busqueda']),
            // Quien administra programas (admin o empresa) también ve y filtra borradores y archivados.
            'veTodosLosEstados' => $isAdmin || $isEmpresa,
            'esAdmin' => $isAdmin,
        ]);
    }

    public function show(Request $request, Programa $programa, ColaDeInformes $cola, PgpService $pgp): InertiaResponse|RedirectResponse
    {
        // Un programa público al que el rango del investigador aún no llega se explica en lugar de dar un 403 seco.
        $rangos = app(Rangos::class);
        $usuario = $request->user();

        if (
            ! $this->puedeProgramAction(AccionesAbac::ProgramaVer, $programa)
            && $programa->es_publico
            && in_array($programa->estado->value, ['activo', 'en_pausa'], true)
            && $usuario->tieneRol('investigador')
            && ! in_array($programa->nivel_acceso->value, $rangos->nivelesAccesibles((int) $usuario->reputation_score), true)
        ) {
            $nivel = collect($rangos->paraInterfaz()['niveles'])->firstWhere('valor', $programa->nivel_acceso->value);

            return redirect()->route('programas.index')->with(
                'error',
                "«{$programa->nombre}» exige el rango {$nivel['rangoNombre']} ({$nivel['minimo']}+ pts). Sigue enviando informes válidos para subir de rango.",
            );
        }

        $this->authorizeProgramAction(AccionesAbac::ProgramaVer, $programa);

        // Nunca se envía la relación `reportes`: contendría informes de otros investigadores.
        $programa->load(['creador', 'objetivos', 'empresa:id,razon_social,nombre_comercial,sitio_web']);
        $programa->loadCount(['reportes' => fn ($query) => $query->where('estado', '!=', 'borrador')]);

        $puedeReportar = Gate::allows('abac', [AccionesAbac::ReporteCrear, $programa]);
        $puedeGestionar = $this->puedeProgramAction(AccionesAbac::ProgramaGestionar, $programa);
        $puedeEditar = $this->puedeProgramAction(AccionesAbac::ProgramaEditar, $programa);
        $puedeCambiarEstado = $this->puedeProgramAction(AccionesAbac::ProgramaCambiarEstado, $programa);
        $puedeEliminar = $this->puedeProgramAction(AccionesAbac::ProgramaEliminar, $programa);
        $puedeInvitarHackers = $this->puedeProgramAction(AccionesAbac::ProgramaInvitarHacker, $programa);

        $transicionesPermitidas = $puedeCambiarEstado
            ? self::TRANSICIONES_VALIDAS[$programa->estado->value]
            : [];

        // Los moderadores y admins ven ahí mismo los informes del programa para revisarlos.
        // Misma regla que el panel de moderación: el admin no opera la cola de informes.
        $puedeModerar = Gate::allows('abac', [AccionesAbac::ModeracionVer])
            && $request->user()->puedeModerarPrograma($programa);
        $filtroInformes = ColaDeInformes::filtro($request->input('filtro'));

        // Investigadores invitados al programa privado (para la empresa que gestiona el programa)
        $hackersInvitados = $puedeInvitarHackers
            ? InvitacionPrograma::query()
                ->where('programa_id', $programa->id)
                ->with('investigador:id,name,email,reputation_score')
                ->get()
                ->map(fn (InvitacionPrograma $invitacion) => [
                    'id' => $invitacion->investigador->id,
                    'name' => $invitacion->investigador->name,
                    'email' => $invitacion->investigador->email,
                    'reputation_score' => $invitacion->investigador->reputation_score,
                    'estado' => $invitacion->estado,
                ])->all()
            : [];

        // El alcance (descripcion, bugs_buscados y objetivos) queda cifrado en la base:
        // se descifra recién acá, al entrar al detalle de este programa puntual.
        $alcance = $this->alcanceDescifrado($programa, $pgp);
        $programasCiegos = Reporte::programasEnTriajeCiego($request->user());

        return Inertia::render('programas/Show', [
            'puedeEditar' => $puedeEditar,
            'puedeModerar' => $puedeModerar,
            'filtroInformes' => $filtroInformes,
            'conteosInformes' => $puedeModerar ? $cola->conteos($programa) : null,
            'informes' => $puedeModerar
                ? $cola->consulta($programa, $filtroInformes, $request->user())->limit(10)->get()
                    ->map(fn (Reporte $reporte): array => $cola->resumen($reporte, $request->user(), $programasCiegos))->all()
                : [],
            'programa' => [
                ...$programa->toArray(),
                'descripcion' => $alcance['descripcion'],
                'bugs_buscados' => $alcance['bugs_buscados'],
                'empresa' => $programa->empresa === null ? null : [
                    'nombre' => $programa->empresa->nombre_comercial ?? $programa->empresa->razon_social,
                    'sitio_web' => $programa->empresa->sitio_web,
                ],
                // El autor solo es relevante para quien gestiona el programa.
                'creador' => $puedeGestionar ? $programa->creador?->only(['id', 'name']) : null,
                'objetivos' => $alcance['objetivos'],
            ],
            'cifradoIndisponible' => $alcance['cifrado_indisponible'],
            'puedeReportar' => $puedeReportar,
            // Un moderador no puede reportar en el programa que modera: se le explica en lugar de ocultar el botón sin más.
            'moderaEstePrograma' => $request->user()->tieneRol('moderador') && in_array($programa->id, $request->user()->idsProgramasModerados(), true),
            // Pertenece a la empresa dueña del programa: no puede reportarle mientras sea miembro.
            'esDeMiEmpresa' => $programa->empresa_id !== null && $programa->empresa_id === $request->user()->idEmpresaActiva(),
            'puedeGestionar' => $puedeGestionar,
            'puedeCambiarEstado' => $puedeCambiarEstado,
            'puedeEliminar' => $puedeEliminar && ! $programa->reportes()->exists(),
            'puedeInvitarHackers' => $puedeInvitarHackers,
            'hackersInvitados' => $hackersInvitados,
            'transicionesPermitidas' => $transicionesPermitidas,
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ProgramaCrear]);

        return Inertia::render('programas/gestion/Create');
    }

    public function edit(Programa $programa, PgpService $pgp): InertiaResponse
    {
        $this->authorizeProgramAction(AccionesAbac::ProgramaEditar, $programa);
        $programa->load(['objetivos']);

        $alcance = $this->alcanceDescifrado($programa, $pgp);

        return Inertia::render('programas/gestion/Edit', [
            'programa' => [
                ...$programa->toArray(),
                'descripcion' => $alcance['descripcion'],
                'bugs_buscados' => $alcance['bugs_buscados'],
                'objetivos' => $alcance['objetivos'],
            ],
            'cifradoIndisponible' => $alcance['cifrado_indisponible'],
        ]);
    }

    public function store(StoreProgramaRequest $request, PgpService $pgp): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $validated['nivel_acceso'] ??= 'bajo';

        // El programa siempre se crea para la empresa del publicador/propietario que lo pide:
        // nadie elige la empresa por otro (ver ABAC: crear/editar/publicar es cosa de la empresa dueña).
        unset($validated['empresa_id']);

        $programa = DB::transaction(function () use ($validated, $user, $pgp) {
            $objetivos = $validated['objetivos'] ?? [];
            unset($validated['objetivos']);

            $validated['creado_por'] = $user->id;
            $validated['estado'] = 'borrador';

            // Hace falta la empresa ANTES de cifrar: el contenido se cifra a su
            // clave (+ la de custodia), no a una clave genérica de plataforma.
            $empresa = null;
            if ($user->empresas()->wherePivot('estado', 'activo')->exists()) {
                $empresa = $user->empresas()
                    ->where('empresas.estado', 'aprobada')
                    ->where('empresa_usuario.estado', 'activo')
                    ->first();

                abort_if($empresa === null, 403, 'La empresa debe estar aprobada para crear programas.');
                $validated['empresa_id'] = $empresa->id;
            }

            // El alcance queda cifrado en la base: solo se descifra al abrir el detalle o editar.
            $cifrado = $pgp->cifrarPrograma($validated['descripcion'], $validated['bugs_buscados'] ?? null, $empresa);
            $validated['descripcion'] = $cifrado['descripcion'];
            $validated['bugs_buscados'] = $cifrado['bugs_buscados'];

            $programa = Programa::create($validated);

            foreach ($objetivos as $objetivo) {
                $programa->objetivos()->create([
                    ...$objetivo,
                    ...$pgp->cifrarObjetivo($objetivo['valor'], $objetivo['descripcion'] ?? null, $empresa),
                ]);
            }

            return $programa;
        });

        Auditoria::registrar('programas.creado', $programa, ['nombre' => $programa->nombre], $user->id);

        return redirect()->route('programas.show', $programa)
            ->with('success', 'Programa creado exitosamente.');
    }

    public function update(UpdateProgramaRequest $request, Programa $programa, PgpService $pgp): RedirectResponse
    {
        $validated = $request->validated();
        $objetivos = $validated['objetivos'] ?? null;
        unset($validated['objetivos']);

        // La empresa dueña no cambia en un update (el request ya la descarta), así
        // que sigue siendo la misma clave a la que estaba cifrado el programa.
        $empresa = $programa->empresa;

        if (array_key_exists('descripcion', $validated)) {
            $cifrado = $pgp->cifrarPrograma($validated['descripcion'], $validated['bugs_buscados'] ?? null, $empresa);
            $validated['descripcion'] = $cifrado['descripcion'];
            $validated['bugs_buscados'] = $cifrado['bugs_buscados'];
        }

        DB::transaction(function () use ($programa, $validated, $objetivos, $pgp, $empresa) {
            $programa->update($validated);

            if ($objetivos !== null) {
                // Se conservan los objetivos que siguen en el formulario (por id), se crean los
                // nuevos y solo se borran los que el usuario quitó.
                $conservados = [];
                foreach ($objetivos as $datos) {
                    $id = $datos['id'] ?? null;
                    unset($datos['id']);

                    $datos = [...$datos, ...$pgp->cifrarObjetivo($datos['valor'], $datos['descripcion'] ?? null, $empresa)];

                    $objetivo = $id === null ? null : $programa->objetivos()->whereKey($id)->first();

                    if ($objetivo !== null) {
                        $objetivo->update($datos);
                    } else {
                        $objetivo = $programa->objetivos()->create($datos);
                    }

                    $conservados[] = $objetivo->id;
                }

                $programa->objetivos()->whereNotIn('id', $conservados)->delete();
            }
        });

        Auditoria::registrar('programas.editado', $programa);

        return redirect()->route('programas.show', $programa)
            ->with('success', 'Programa actualizado exitosamente.');
    }

    public function destroy(Programa $programa): RedirectResponse
    {
        $this->authorizeProgramAction(AccionesAbac::ProgramaEliminar, $programa);

        // Los informes dependen del programa (listados, cola de moderación, timeline):
        // uno que ya recibió informes se archiva, no se elimina.
        if ($programa->reportes()->exists()) {
            throw ValidationException::withMessages([
                'programa' => 'Este programa ya recibió informes y no se puede eliminar. Archívalo para que deje de aceptar nuevos reportes.',
            ]);
        }

        $programa->delete();

        Auditoria::registrar('programas.eliminado', $programa, ['nombre' => $programa->nombre]);

        $esEmpresa = request()->user()?->roles()->where('slug', 'empresa')->exists() ?? false;

        return redirect()->route($esEmpresa ? 'empresa.dashboard' : 'programas.index')
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

        // Sin objetivos no hay alcance definido: los investigadores no sabrían qué investigar.
        if ($estadoDestino === 'activo' && ! $programa->objetivos()->exists()) {
            throw ValidationException::withMessages([
                'estado' => 'Define al menos un objetivo (qué sistemas se pueden investigar) antes de publicar el programa.',
            ]);
        }

        $programa->update(['estado' => $estadoDestino]);

        Auditoria::registrar('programas.estado_cambiado', $programa, ['estado_anterior' => $estadoActual, 'estado_nuevo' => $estadoDestino]);

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

        $query = Programa::query()->with(['creador', 'objetivos'])
            ->gestionablesPor($user);

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('busqueda')) {
            // La descripcion queda cifrada en la base: no se puede buscar por ella con LIKE.
            $query->where('nombre', 'like', '%'.$request->input('busqueda').'%');
        }

        $programas = $query->withCount('reportes')->orderBy('nombre')->paginate(15)->withQueryString();
        $programas->getCollection()->each(
            fn (Programa $programa) => $programa->setAttribute(
                'puede_editar',
                $this->puedeProgramAction(AccionesAbac::ProgramaEditar, $programa),
            ),
        );

        return Inertia::render('programas/gestion/Index', [
            'programas' => $programas,
            'filtros' => $request->only(['estado', 'busqueda']),
            'esAdmin' => $isAdmin,
        ]);
    }

    public function invitarHacker(Request $request, Programa $programa): RedirectResponse
    {
        $this->authorizeProgramAction(AccionesAbac::ProgramaInvitarHacker, $programa);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $investigador = User::where('email', $validated['email'])->first();
        if (! $investigador || ! $investigador->tieneRol('investigador')) {
            throw ValidationException::withMessages([
                'email' => 'No se encontró ningún investigador registrado con ese correo electrónico.',
            ]);
        }

        if ($programa->hackersInvitados()->where('users.id', $investigador->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Este investigador ya tiene una invitación para este programa.',
            ]);
        }

        $programa->hackersInvitados()->attach($investigador->id, [
            'invitado_por' => $request->user()->id,
            'estado' => 'pendiente',
        ]);

        Auditoria::registrar('programas.hacker_invitado', $programa, [
            'investigador_id' => $investigador->id,
            'investigador_email' => $investigador->email,
        ], $request->user()->id);

        return redirect()->back()->with('success', "Investigador {$investigador->name} invitado al programa privado exitosamente.");
    }

    public function cancelarInvitacionHacker(Programa $programa, User $user): RedirectResponse
    {
        $this->authorizeProgramAction(AccionesAbac::ProgramaInvitarHacker, $programa);

        $programa->hackersInvitados()->detach($user->id);

        Auditoria::registrar('programas.hacker_invitacion_cancelada', $programa, [
            'investigador_id' => $user->id,
        ], request()->user()->id);

        return redirect()->back()->with('success', 'Invitación removida exitosamente.');
    }

    /**
     * @return array{descripcion: string|null, bugs_buscados: string|null, objetivos: array<int, array<string, mixed>>, cifrado_indisponible: bool}
     */
    private function alcanceDescifrado(Programa $programa, PgpService $pgp): array
    {
        $cifradoIndisponible = false;

        try {
            $descifrado = $pgp->descifrarPrograma($programa->descripcion, $programa->bugs_buscados, $programa);
            $descripcion = $descifrado['descripcion'];
            $bugsBuscados = $descifrado['bugs_buscados'];
        } catch (PgpException $e) {
            report($e);
            $cifradoIndisponible = true;
            $descripcion = null;
            $bugsBuscados = null;
        }

        $objetivos = $programa->objetivos->map(function (ObjetivoPrograma $o) use ($pgp, &$cifradoIndisponible): array {
            try {
                $objetivoDescifrado = $pgp->descifrarObjetivo($o->valor, $o->descripcion, $o);

                return [...$o->toArray(), ...$objetivoDescifrado];
            } catch (PgpException $e) {
                report($e);
                $cifradoIndisponible = true;

                return [...$o->toArray(), 'valor' => '[Contenido no disponible]', 'descripcion' => null];
            }
        })->all();

        return [
            'descripcion' => $descripcion,
            'bugs_buscados' => $bugsBuscados,
            'objetivos' => $objetivos,
            'cifrado_indisponible' => $cifradoIndisponible,
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Abac\AbacEngine;
use App\Abac\AccionesAbac;
use App\Abac\ExplicadorAbac;
use App\Http\Controllers\Controller;
use App\Models\Apelacion;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AbacSimuladorController extends Controller
{
    /**
     * Acciones que se pueden simular, con el tipo de objeto sobre el que se evalúan en la
     * aplicación: sin ese objeto (p. ej. «gestionar programa» sin programa) el motor no puede
     * comprobar a quién pertenece y deniega.
     *
     * @var array<string, array{label: string, objeto: string}>
     */
    public const ACCIONES = [
        'reportes.crear' => ['label' => 'Crear un informe en un programa', 'objeto' => 'programa'],
        'reportes.ver' => ['label' => 'Ver un informe', 'objeto' => 'reporte'],
        'reportes.editar' => ['label' => 'Editar un informe', 'objeto' => 'reporte'],
        'reportes.enviar' => ['label' => 'Enviar un informe a moderación', 'objeto' => 'reporte'],
        'reportes.asignar' => ['label' => 'Asignar un informe a un moderador', 'objeto' => 'reporte'],
        'reportes.revisar' => ['label' => 'Iniciar la revisión de un informe', 'objeto' => 'reporte'],
        'reportes.validar' => ['label' => 'Validar un informe', 'objeto' => 'reporte'],
        'reportes.rechazar' => ['label' => 'Rechazar un informe', 'objeto' => 'reporte'],
        'reportes.marcar_duplicado' => ['label' => 'Marcar un informe como duplicado', 'objeto' => 'reporte'],
        'reportes.ajustar_cvss' => ['label' => 'Ajustar el CVSS de un informe', 'objeto' => 'reporte'],
        'reportes.marcar_en_reparacion' => ['label' => 'Marcar un informe en reparación', 'objeto' => 'reporte'],
        'reportes.cerrar' => ['label' => 'Cerrar un informe como resuelto', 'objeto' => 'reporte'],
        'reportes.ver_notas_internas' => ['label' => 'Ver las notas internas de un informe', 'objeto' => 'reporte'],
        'programas.crear' => ['label' => 'Crear un programa', 'objeto' => 'ninguno'],
        'programas.ver' => ['label' => 'Ver un programa', 'objeto' => 'programa'],
        'programas.gestionar' => ['label' => 'Gestionar un programa', 'objeto' => 'programa'],
        'programas.editar' => ['label' => 'Editar un programa', 'objeto' => 'programa'],
        'programas.cambiar_estado' => ['label' => 'Publicar, pausar o cerrar un programa', 'objeto' => 'programa'],
        'programas.invitar_hacker' => ['label' => 'Invitar investigadores a un programa privado', 'objeto' => 'programa'],
        'certificados.ver' => ['label' => 'Ver el certificado de un informe', 'objeto' => 'reporte'],
        'apelaciones.crear' => ['label' => 'Apelar una sanción', 'objeto' => 'apelacion'],
        'apelaciones.resolver' => ['label' => 'Resolver una apelación', 'objeto' => 'apelacion'],
        'moderacion.ver' => ['label' => 'Entrar a la cola de moderación', 'objeto' => 'ninguno'],
    ];

    /**
     * Muestra la interfaz del simulador visual de políticas ABAC.
     */
    public function index(AbacEngine $engine): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::AbacSimular]);

        $usuarios = User::query()
            ->with('roles:id,slug,nombre')
            ->select(['id', 'name', 'email', 'reputation_score'])
            ->orderBy('name')
            ->limit(50)
            ->get();
        $casos = $this->casosDeEjemplo($engine);
        $faltan = array_diff(array_column($casos, 'usuario_id'), $usuarios->pluck('id')->all());
        $usuarios = $usuarios->concat(User::query()->with('roles:id,slug,nombre')->whereKey($faltan)->get(['id', 'name', 'email', 'reputation_score']))
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'reputation_score' => $u->reputation_score,
                'roles' => $u->roles->pluck('slug')->all(),
            ]);

        $acciones = self::ACCIONES;

        $idsDe = fn (string $tipo): array => array_column(array_filter($casos, fn (array $c): bool => $c['tipo_recurso'] === $tipo), 'recurso_id');

        $reportes = Reporte::query()
            ->with(['programa:id,nombre', 'investigador:id,name'])
            ->select(['id', 'numero_reporte', 'titulo', 'estado', 'programa_id', 'investigador_id', 'asignado_a'])
            ->latest()
            ->limit(30)
            ->get()
            ->concat(Reporte::query()->with(['programa:id,nombre', 'investigador:id,name'])->whereKey($idsDe('reporte'))->get())
            ->unique('id')
            ->map(fn (Reporte $r) => [
                'id' => $r->id,
                'etiqueta' => "{$r->numero_reporte} — {$r->titulo} ({$r->estado->value})",
                'programa' => $r->programa->nombre,
                'investigador' => $r->investigador->name,
                'estado' => $r->estado->value,
            ])
            ->values();

        $programas = Programa::query()
            ->select(['id', 'nombre', 'estado', 'es_publico', 'empresa_id', 'nivel_acceso'])
            ->latest()
            ->limit(30)
            ->get()
            ->concat(Programa::query()->whereKey($idsDe('programa'))->get(['id', 'nombre', 'estado', 'es_publico', 'empresa_id', 'nivel_acceso']))
            ->unique('id')
            ->map(fn (Programa $p) => [
                'id' => $p->id,
                'etiqueta' => "{$p->nombre} [".($p->es_publico ? 'Público' : 'Privado')." / {$p->estado->value}]",
                'es_publico' => $p->es_publico,
                'estado' => $p->estado->value,
            ])
            ->values();

        $apelaciones = Apelacion::query()
            ->with(['sancion:id,motivo,gravedad'])
            ->select(['id', 'estado', 'sancion_id', 'usuario_id'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Apelacion $a) => [
                'id' => $a->id,
                'etiqueta' => "Apelación #{$a->id} ({$a->estado->value}) — Motivo sanción: {$a->sancion->motivo}",
                'estado' => $a->estado->value,
            ]);

        return Inertia::render('admin/abac/Simulador', [
            'usuarios' => $usuarios,
            'acciones' => $acciones,
            'recursos' => [
                'reportes' => $reportes,
                'programas' => $programas,
                'apelaciones' => $apelaciones,
            ],
            'casos' => $casos,
            'totalReglas' => count(config('abac.reglas', [])),
            'denyByDefault' => (bool) config('abac.deny_by_default', true),
        ]);
    }

    /**
     * Evalúa una petición ABAC en tiempo real y devuelve el árbol de decisión desglosado.
     */
    public function simular(Request $request, AbacEngine $engine, ExplicadorAbac $explicador): JsonResponse
    {
        Gate::authorize('abac', [AccionesAbac::AbacSimular]);

        $request->validate([
            'usuario_id' => 'nullable|exists:users,id',
            'accion' => 'required|string',
            'tipo_recurso' => 'nullable|in:reporte,programa,apelacion,ninguno',
            'recurso_id' => 'nullable|integer',
        ]);

        $sujetoId = $request->integer('usuario_id');
        /** @var User|null $sujeto */
        $sujeto = $sujetoId > 0 ? User::query()->find($sujetoId) : null;
        $tipoRecurso = $request->input('tipo_recurso');
        $recursoId = $request->input('recurso_id');

        $objeto = match ($tipoRecurso) {
            'reporte' => is_numeric($recursoId) ? Reporte::query()->find((int) $recursoId) : null,
            'programa' => is_numeric($recursoId) ? Programa::query()->find((int) $recursoId) : null,
            'apelacion' => is_numeric($recursoId) ? Apelacion::query()->find((int) $recursoId) : null,
            default => null,
        };

        $accion = (string) $request->accion;

        // El mismo entorno que arma la aplicación en cada petición: la empresa aprobada en la que
        // el sujeto está activo (ver ProgramaController::argumentosAbac). Sin él, el simulador
        // negaría lo que la app permite (p. ej. que la empresa vea su propio programa en borrador).
        $entorno = $this->entornoDe($sujeto);

        $contexto = $engine->contexto($accion, $objeto, $sujeto, $entorno);
        $decision = $engine->evaluar($accion, $objeto, $sujeto, $entorno);

        return response()->json([
            'permitido' => $decision->estaPermitida(),
            'decision' => $decision->decision->etiqueta(),
            'motivo' => $decision->motivo(),
            'regla_decisiva' => $decision->regla,
            'contexto' => [
                'sujeto' => $contexto->sujeto,
                'objeto' => $contexto->objetoAttrs,
                'entorno' => $contexto->entorno,
            ],
            'detalle' => $decision->detalle,
            'explicacion' => $explicador->explicar($decision, $contexto, [
                'accion' => self::ACCIONES[$accion]['label'] ?? $accion,
                'sujeto' => $sujeto?->name,
                'objeto' => $this->etiquetaObjeto($objeto),
            ]),
        ]);
    }

    /**
     * Casos de ejemplo sacados de los datos reales, para la demo con un clic. El resultado que
     * se anuncia lo calcula el propio motor: nunca promete algo que luego no pase.
     *
     * @return array<int, array{titulo: string, usuario_id: int, accion: string, tipo_recurso: string, recurso_id: int|null, permitido: bool}>
     */
    private function casosDeEjemplo(AbacEngine $engine): array
    {
        $conRol = fn (string $rol) => User::query()->whereHas('roles', fn ($q) => $q->where('slug', $rol));
        $investigadores = $conRol('investigador')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('slug', ['moderador', 'administrador', 'empresa']))
            ->limit(40)
            ->get();
        $publico = Programa::query()->where('estado', 'activo')->where('es_publico', true)->where('solo_verificados', false)->orderBy('nivel_acceso')->first();
        $triaje = Reporte::query()->whereIn('estado', Reporte::ESTADOS_PENDIENTES)->whereNotNull('asignado_a')->first();
        $moderador = $triaje?->asignadoA;
        $ajeno = $moderador === null ? null : Reporte::query()->whereIn('estado', Reporte::ESTADOS_PENDIENTES)
            ->where(fn ($q) => $q->whereNull('asignado_a')->orWhere('asignado_a', '!=', $moderador->id))
            ->first();
        $empresa = $conRol('empresa')
            ->whereHas('empresas', fn ($q) => $q->where('empresas.estado', 'aprobada')->where('empresa_usuario.estado', 'activo'))
            ->first();
        $idEmpresa = $this->entornoDe($empresa)['empresa_id'] ?? null;
        $propio = $idEmpresa === null ? null : Programa::query()->where('empresa_id', $idEmpresa)->first();
        $deOtra = $idEmpresa === null ? null : Programa::query()->where('empresa_id', '!=', $idEmpresa)->first();
        $apelacion = Apelacion::query()->latest()->first();

        $propuestas = [
            ['Investigador reporta en un programa público', $investigadores->first(fn (User $u) => $u->suspensionActiva() === null), 'reportes.crear', $publico],
            ['Investigador suspendido intenta reportar', $investigadores->first(fn (User $u) => $u->suspensionActiva() !== null), 'reportes.crear', $publico],
            ['Empresa intenta enviar un informe', $empresa, 'reportes.crear', $publico],
            ['Moderador valida un informe que tiene asignado', $moderador, 'reportes.validar', $triaje],
            ['El mismo moderador valida un informe que no es suyo', $moderador, 'reportes.validar', $ajeno],
            ['El autor intenta validar su propio informe', $triaje?->investigador, 'reportes.validar', $triaje],
            ['Empresa gestiona su propio programa', $empresa, 'programas.gestionar', $propio],
            ['Empresa intenta gestionar un programa de otra', $empresa, 'programas.gestionar', $deOtra],
            ['Moderador intenta resolver una apelación', $moderador, 'apelaciones.resolver', $apelacion],
        ];

        $casos = [];
        foreach ($propuestas as [$titulo, $usuario, $accion, $objeto]) {
            if (! $usuario instanceof User || ($objeto === null && self::ACCIONES[$accion]['objeto'] !== 'ninguno')) {
                continue;
            }
            $casos[] = [
                'titulo' => $titulo,
                'usuario_id' => (int) $usuario->id,
                'accion' => $accion,
                'tipo_recurso' => self::ACCIONES[$accion]['objeto'],
                'recurso_id' => $objeto === null ? null : (int) $objeto->getKey(),
                'permitido' => $engine->evaluar($accion, $objeto, $usuario, $this->entornoDe($usuario))->estaPermitida(),
            ];
        }

        return $casos;
    }

    /**
     * El mismo entorno que arma la aplicación en cada petición: la empresa aprobada en la que el
     * usuario está activo (ver ProgramaController::argumentosAbac).
     *
     * @return array{empresa_id?: int}
     */
    private function entornoDe(?User $usuario): array
    {
        $empresa = $usuario?->empresas()
            ->where('empresas.estado', 'aprobada')
            ->where('empresa_usuario.estado', 'activo')
            ->first();

        return $empresa === null ? [] : ['empresa_id' => (int) $empresa->id];
    }

    private function etiquetaObjeto(mixed $objeto): ?string
    {
        return match (true) {
            $objeto instanceof Reporte => "{$objeto->numero_reporte} — {$objeto->titulo}",
            $objeto instanceof Programa => $objeto->nombre,
            $objeto instanceof Apelacion => "Apelación #{$objeto->id}",
            default => null,
        };
    }
}

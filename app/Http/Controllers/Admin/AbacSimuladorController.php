<?php

namespace App\Http\Controllers\Admin;

use App\Abac\AbacEngine;
use App\Http\Controllers\Controller;
use App\Models\Apelacion;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AbacSimuladorController extends Controller
{
    /**
     * Muestra la interfaz del simulador visual de políticas ABAC.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        if (! $user->tieneRol('administrador')) {
            abort(403, 'Solo los administradores pueden acceder al simulador de políticas ABAC.');
        }

        $usuarios = User::query()
            ->with('roles:id,slug,nombre')
            ->select(['id', 'name', 'email', 'reputation_score'])
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'reputation_score' => $u->reputation_score,
                'roles' => $u->roles->pluck('slug')->all(),
            ]);

        $acciones = [
            'reportes.crear' => 'Crear nuevo reporte',
            'reportes.ver' => 'Ver reporte específico',
            'reportes.editar' => 'Editar reporte',
            'reportes.enviar' => 'Enviar reporte a moderación',
            'reportes.asignar' => 'Asignar reporte a moderador',
            'reportes.revisar' => 'Iniciar revisión (tomar informe)',
            'reportes.validar' => 'Validar hallazgo (aprobar)',
            'reportes.rechazar' => 'Rechazar reporte (descartar)',
            'reportes.marcar_duplicado' => 'Marcar reporte como duplicado',
            'reportes.marcar_en_reparacion' => 'Marcar informe en reparación',
            'reportes.cerrar' => 'Cerrar reporte como resuelto',
            'reportes.ver_notas_internas' => 'Ver notas internas confidenciales',
            'programas.crear' => 'Crear nuevo programa',
            'programas.ver' => 'Ver programa y objetivos',
            'programas.gestionar' => 'Gestionar programa propio',
            'programas.editar' => 'Editar alcance de programa',
            'programas.cambiar_estado' => 'Cambiar estado del programa',
            'programas.invitar_hacker' => 'Invitar hacker a programa privado',
            'apelaciones.crear' => 'Presentar apelación contra sanción',
            'apelaciones.resolver' => 'Resolver apelación (exclusivo admin)',
            'moderacion.ver' => 'Acceder al panel de moderación',
        ];

        $reportes = Reporte::query()
            ->with(['programa:id,nombre', 'investigador:id,name'])
            ->select(['id', 'numero_reporte', 'titulo', 'estado', 'programa_id', 'investigador_id', 'asignado_a'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Reporte $r) => [
                'id' => $r->id,
                'etiqueta' => "{$r->numero_reporte} — {$r->titulo} ({$r->estado->value})",
                'programa' => $r->programa->nombre,
                'investigador' => $r->investigador->name,
                'estado' => $r->estado->value,
            ]);

        $programas = Programa::query()
            ->select(['id', 'nombre', 'estado', 'es_publico', 'empresa_id', 'nivel_acceso'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Programa $p) => [
                'id' => $p->id,
                'etiqueta' => "{$p->nombre} [".($p->es_publico ? 'Público' : 'Privado')." / {$p->estado->value}]",
                'es_publico' => $p->es_publico,
                'estado' => $p->estado->value,
            ]);

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
            'totalReglas' => count(config('abac.reglas', [])),
            'denyByDefault' => (bool) config('abac.deny_by_default', true),
        ]);
    }

    /**
     * Evalúa una petición ABAC en tiempo real y devuelve el árbol de decisión desglosado.
     */
    public function simular(Request $request, AbacEngine $engine): JsonResponse
    {
        $user = $request->user();
        if (! $user->tieneRol('administrador')) {
            abort(403);
        }

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

        $contexto = $engine->contexto($accion, $objeto, $sujeto);
        $decision = $engine->evaluar($accion, $objeto, $sujeto);

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
        ]);
    }
}

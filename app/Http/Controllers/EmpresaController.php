<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Empresas\MembresiaEmpresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

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

        // La empresa solo ve informes triados o en revisión formal: nunca borradores, pre-triaje ('enviado') ni rechazados.
        $programas = $empresa->programas()
            ->withCount([
                'objetivos',
                'reportes as reportes_todos',
                'reportes as reportes_total' => fn ($query) => $query->whereIn('estado', Reporte::ESTADOS_VISIBLES_EMPRESA),
                'reportes as reportes_validados' => fn ($query) => $query->where('estado', 'validado'),
                'reportes as reportes_en_reparacion' => fn ($query) => $query->where('estado', 'en_reparacion'),
                'reportes as reportes_cerrados' => fn ($query) => $query->where('estado', 'cerrado'),
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
                'programas' => $programas,
                'resumen' => [
                    'programas' => $programas->count(),
                    'reportes' => $programas->sum('reportes_total'),
                    'validados' => $programas->sum('reportes_validados'),
                    'en_reparacion' => $programas->sum('reportes_en_reparacion'),
                    'cerrados' => $programas->sum('reportes_cerrados'),
                ],
                'reportes' => $this->reportesRecientes($empresa),
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
        // El admin elige la empresa (sin pivot); el resto la toma de su membresía activa.
        $rolInterno = null;

        if ($esAdmin) {
            $empresa = $this->empresaElegida($request, 'empresa');
        } else {
            $empresa = $request->user()
                ->empresas()
                ->where('empresa_usuario.estado', 'activo')
                ->latest('empresas.created_at')
                ->first();
            $rolInterno = $empresa?->pivot->rol_interno;
        }

        abort_if($empresa === null, $esAdmin ? 404 : 403, $esAdmin ? 'Elige una empresa.' : 'Tu usuario no pertenece a una empresa.');
        abort_unless($empresa->estado === EstadoEmpresa::Aprobada, 403, 'Tu empresa todavía no tiene acceso operativo.');
        abort_if(! $esAdmin && $rolInterno !== MembresiaEmpresa::PROPIETARIO, 403, 'Solo el propietario de la empresa ve los informes que recibe.');

        $filtro = in_array($request->input('filtro'), ['todos', 'validados', 'en_reparacion', 'cerrados', 'descartados'], true)
            ? (string) $request->input('filtro')
            : 'todos';
        $programaId = $request->filled('programa_id') ? (int) $request->input('programa_id') : null;

        $recibidos = fn () => Reporte::query()
            ->whereIn('programa_id', $empresa->programas()->select('programas.id'))
            ->whereIn('estado', Reporte::ESTADOS_VISIBLES_EMPRESA);

        $reportes = $recibidos()
            ->with(['programa:id,nombre', 'investigador:id,name,reputation_score'])
            ->when($programaId !== null, fn ($query) => $query->where('programa_id', $programaId))
            ->when($request->filled('busqueda'), function ($query) use ($request) {
                $busqueda = (string) $request->input('busqueda');
                $query->where(fn ($q) => $q->where('titulo', 'like', "%{$busqueda}%")->orWhere('numero_reporte', 'like', "%{$busqueda}%"));
            })
            ->when($filtro === 'validados', fn ($query) => $query->where('estado', 'validado'))
            ->when($filtro === 'en_reparacion', fn ($query) => $query->where('estado', 'en_reparacion'))
            ->when($filtro === 'cerrados', fn ($query) => $query->where('estado', 'cerrado'))
            ->when($filtro === 'descartados', fn ($query) => $query->whereIn('estado', Reporte::ESTADOS_RECHAZADOS))
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
                'validados' => $recibidos()->where('estado', 'validado')->count(),
                'en_reparacion' => $recibidos()->where('estado', 'en_reparacion')->count(),
                'cerrados' => $recibidos()->where('estado', 'cerrado')->count(),
                // Lo que moderación descartó (rechazado, duplicado, fuera de alcance): la empresa lo revisa igual.
                'descartados' => $recibidos()->whereIn('estado', Reporte::ESTADOS_RECHAZADOS)->count(),
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
            ->whereIn('estado', Reporte::ESTADOS_VISIBLES_EMPRESA)
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

    private function esAdministrador(User $usuario): bool
    {
        return $usuario->roles()->where('slug', 'administrador')->exists();
    }

    /** Empresa indicada en la petición (solo la usa el administrador para operar cualquier empresa). */
    private function empresaElegida(Request $request, string $campo): ?Empresa
    {
        return $request->filled($campo) ? Empresa::query()->find((int) $request->input($campo)) : null;
    }
}

<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Models\Programa;
use App\Models\Reporte;
use App\Services\Moderacion\ColaDeInformes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Cola de moderación: programas con informes recibidos y los informes de
 * cada programa, para que moderadores y administradores los revisen.
 */
class ModeracionController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ModeracionVer]);

        $usuario = $request->user();
        $recibidos = fn () => Reporte::query()->where('estado', '!=', 'borrador');

        $programas = Programa::query()
            ->with('empresa:id,razon_social,nombre_comercial')
            ->withCount([
                'reportes as reportes_total' => fn ($query) => $query->where('estado', '!=', 'borrador'),
                'reportes as reportes_por_revisar' => fn ($query) => $query->where('estado', 'enviado'),
                'reportes as reportes_en_revision' => fn ($query) => $query->where('estado', 'en_revision'),
                'reportes as reportes_aprobados' => fn ($query) => $query->whereIn('estado', Reporte::ESTADOS_APROBADOS),
                'reportes as reportes_rechazados' => fn ($query) => $query->whereIn('estado', Reporte::ESTADOS_RECHAZADOS),
            ])
            ->when($request->filled('busqueda'), function ($query) use ($request) {
                $busqueda = (string) $request->input('busqueda');
                $query->where('nombre', 'like', "%{$busqueda}%");
            })
            ->when($request->boolean('solo_pendientes'), function ($query) {
                $query->whereHas('reportes', fn ($reportes) => $reportes->whereIn('estado', Reporte::ESTADOS_PENDIENTES));
            })
            ->orderByDesc('reportes_por_revisar')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Programa $programa): array => [
                'id' => $programa->id,
                'nombre' => $programa->nombre,
                'estado' => $programa->estado->value,
                'empresa' => $programa->empresa === null
                    ? null
                    : ($programa->empresa->nombre_comercial ?? $programa->empresa->razon_social),
                'reportes_total' => (int) $programa->getAttribute('reportes_total'),
                'reportes_por_revisar' => (int) $programa->getAttribute('reportes_por_revisar'),
                'reportes_en_revision' => (int) $programa->getAttribute('reportes_en_revision'),
                'reportes_aprobados' => (int) $programa->getAttribute('reportes_aprobados'),
                'reportes_rechazados' => (int) $programa->getAttribute('reportes_rechazados'),
            ]);

        // Los informes más antiguos pendientes, para atenderlos sin abrir cada programa.
        $porRevisar = $recibidos()
            ->where('estado', 'enviado')
            ->with(['programa:id,nombre', 'investigador:id,name,reputation_score'])
            ->orderBy('enviado_en')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (Reporte $reporte): array => [
                'id' => $reporte->id,
                'numero_reporte' => $reporte->numero_reporte,
                'titulo' => $reporte->titulo,
                'estado' => $reporte->estado->value,
                'severidad' => $reporte->severidad?->value,
                'programa_nombre' => $reporte->programa->nombre,
                'enviado_en' => $reporte->enviado_en?->toISOString(),
                'investigador' => [
                    'id' => $reporte->investigador->id,
                    'name' => $reporte->investigador->name,
                    'reputation_score' => $reporte->investigador->reputation_score,
                ],
            ])
            ->all();

        return Inertia::render('moderacion/Index', [
            'porRevisar' => $porRevisar,
            'programas' => $programas,
            'filtros' => [
                'busqueda' => $request->input('busqueda', ''),
                'solo_pendientes' => $request->boolean('solo_pendientes'),
            ],
            'resumen' => [
                'por_revisar' => $recibidos()->where('estado', 'enviado')->count(),
                'en_revision' => $recibidos()->where('estado', 'en_revision')->count(),
                'mis_asignados' => $recibidos()->where('estado', 'en_revision')->where('asignado_a', $usuario->id)->count(),
                'aprobados' => $recibidos()->whereIn('estado', Reporte::ESTADOS_APROBADOS)->count(),
                'rechazados' => $recibidos()->whereIn('estado', Reporte::ESTADOS_RECHAZADOS)->count(),
            ],
        ]);
    }

    public function programa(Request $request, Programa $programa, ColaDeInformes $cola): InertiaResponse
    {
        Gate::authorize('abac', [AccionesAbac::ModeracionVer]);

        $filtro = ColaDeInformes::filtro($request->input('filtro'));

        return Inertia::render('moderacion/Programa', [
            'programa' => [
                'id' => $programa->id,
                'nombre' => $programa->nombre,
                'estado' => $programa->estado->value,
                'empresa' => $programa->empresa === null
                    ? null
                    : ($programa->empresa->nombre_comercial ?? $programa->empresa->razon_social),
            ],
            'filtro' => $filtro,
            'conteos' => $cola->conteos($programa),
            'reportes' => $cola->consulta($programa, $filtro)
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Reporte $reporte): array => $cola->resumen($reporte)),
        ]);
    }
}

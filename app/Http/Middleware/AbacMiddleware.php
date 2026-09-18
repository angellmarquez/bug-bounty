<?php

namespace App\Http\Middleware;

use App\Abac\AbacEngine;
use App\Models\Auditoria;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de protección ABAC por ruta.
 *
 * Uso: ->middleware('abac:reportes.ver,reporte')
 *
 * El primer argumento es la acción y el segundo el nombre del parámetro de
 * ruta cuyo modelo ya resolvió el route model binding. Entre la denegación
 * se registra una auditoría `abac.denegado`.
 */
class AbacMiddleware
{
    public function __construct(private readonly AbacEngine $engine) {}

    public function handle(Request $request, Closure $next, string $accion, string $parametro): Response
    {
        $objeto = $request->route($parametro);
        $usuario = $request->user();

        $decision = $this->engine->evaluar($accion, $objeto, $usuario);

        if (! $decision->estaPermitida()) {
            Auditoria::query()->create([
                'usuario_id' => $usuario?->id,
                'accion' => 'abac.denegado',
                'entidad_type' => $objeto instanceof Model ? $objeto::class : null,
                'entidad_id' => $objeto instanceof Model ? $objeto->getKey() : null,
                'detalle' => [
                    'accion' => $accion,
                    'regla' => $decision->regla,
                    'motivo' => $decision->motivo(),
                ],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(403, $decision->motivo());
        }

        return $next($request);
    }
}

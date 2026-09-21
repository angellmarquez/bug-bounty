<?php

namespace App\Http\Middleware;

use App\Enums\EstadoEmpresa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmpresaAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->roles()->where('slug', 'empresa')->exists()) {
            return $next($request);
        }

        // Un administrador nunca queda limitado por su vínculo con una empresa.
        if ($user->roles()->where('slug', 'administrador')->exists()) {
            return $next($request);
        }

        $empresa = $user->empresas()
            ->where('empresa_usuario.estado', 'activo')
            ->latest('empresas.created_at')
            ->first();

        if ($empresa === null) {
            abort(403, 'Tu usuario no pertenece a una empresa activa.');
        }

        if ($empresa->estado === EstadoEmpresa::Aprobada) {
            return $next($request);
        }

        if ($request->is('empresa') || $request->is('empresa/*') || $request->is('settings/*') || $request->is('logout')) {
            return $next($request);
        }

        abort(403, 'Tu empresa todavía no tiene acceso operativo.');
    }
}

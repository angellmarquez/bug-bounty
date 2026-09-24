<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Freno a la creación masiva de cuentas: pocas altas por IP en una ventana de tiempo, tanto
 * de investigadores (registro de Fortify) como de empresas. Un bot que intenta registrar
 * cientos de cuentas (para inflar reputación o hacer spam) recibe 429.
 */
class LimitarRegistros
{
    public const RUTAS = ['register.store', 'empresa.register.store'];

    public const INTENTOS = 5;

    public const VENTANA_SEGUNDOS = 600;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('post') || ! in_array($request->route()?->getName(), self::RUTAS, true)) {
            return $next($request);
        }

        $clave = 'registro|'.$request->ip();

        if (RateLimiter::tooManyAttempts($clave, self::INTENTOS)) {
            abort(429, 'Demasiados registros desde esta conexión. Inténtalo de nuevo en unos minutos.');
        }

        RateLimiter::hit($clave, self::VENTANA_SEGUNDOS);

        return $next($request);
    }
}

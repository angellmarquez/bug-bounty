<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Settings\AparienciaController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // El tema de la cuenta manda (se ve igual en cualquier dispositivo); si no hay sesión,
        // el que recuerda la cookie. Se resuelve aquí para pintar la página sin parpadeo.
        $tema = $request->user()->tema ?? $request->cookie('tema');
        $tema = in_array($tema, AparienciaController::TEMAS, true) ? $tema : 'terminal';

        View::share('tema', $tema);
        View::share('appearance', match ($tema) {
            'corporativo' => 'light',
            'auto' => $request->cookie('appearance') === 'dark' ? 'dark' : 'system',
            default => 'dark',
        });

        return $next($request);
    }
}

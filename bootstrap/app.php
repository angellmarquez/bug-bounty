<?php

use App\Exceptions\PaginasDeError;
use App\Http\Middleware\AbacMiddleware;
use App\Http\Middleware\CabecerasDeSeguridad;
use App\Http\Middleware\EmpresaAccessMiddleware;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LimitarRegistros;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás de un proxy que termina TLS (Render, y cualquier PaaS similar): el
        // contenedor recibe el tráfico en HTTP plano, así que sin esto Laravel genera
        // URLs "http://" (assets, redirects) aunque el visitante esté en HTTPS. El
        // contenedor no es accesible directo desde internet más que a través de ese
        // proxy, así que confiar en todos ("*") es seguro acá.
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'tema', 'sidebar_state']);

        $middleware->alias([
            'abac' => AbacMiddleware::class,
            'empresa.access' => EmpresaAccessMiddleware::class,
        ]);

        $middleware->web(append: [
            CabecerasDeSeguridad::class,
            LimitarRegistros::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Los errores se muestran con una página de la aplicación, no con la pantalla blanca por defecto.
        $exceptions->respond(new PaginasDeError);
    })->create();

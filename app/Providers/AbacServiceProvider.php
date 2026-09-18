<?php

namespace App\Providers;

use App\Abac\AbacEngine;
use App\Abac\AtributosAbac;
use App\Abac\EvaluadorCondiciones;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registra el motor ABAC y el Gate único de autorización.
 *
 * Todas las comprobaciones de la plataforma pasan por una única habilidad
 * `abac` que recibe la acción y el objeto:
 *
 *   Gate::authorize('abac', [AccionesAbac::ReporteVer, $reporte])
 *   Gate::allows('abac', [AccionesAbac::ReporteVer, $reporte])
 *   $middleware->alias(['abac' => AbacMiddleware::class])
 */
class AbacServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AtributosAbac::class);
        $this->app->singleton(EvaluadorCondiciones::class);
        $this->app->singleton(AbacEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(AbacEngine::class)->validarReglas();

        Gate::define('abac', function (User $usuario, string $accion, mixed $objeto = null, array $entorno = []): bool {
            return $this->app->make(AbacEngine::class)
                ->evaluar($accion, $objeto, $usuario, $entorno)
                ->estaPermitida();
        });
    }
}

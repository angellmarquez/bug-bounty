<?php

namespace App\Providers;

use App\Models\ConfiguracionReputacion;
use App\Services\Reputacion\CheatDetectionService;
use App\Services\Reputacion\ReputationService;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Registra los servicios del motor de reputación y detección de trampas.
 */
class ReputacionServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ReputationService::class);
        $this->app->singleton(CheatDetectionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->aplicarConfiguracionPersistida();
    }

    /**
     * Lo que el admin guarda en /admin/config/reputacion vive en
     * `configuraciones_reputacion`, no en `config/reputacion.php` (ese archivo
     * solo define los defaults de fábrica). Acá se sobrescribe `config('reputacion.*')`
     * con esa fila para que el resto de la app (ReputationService, CheatDetectionService,
     * Rangos, etc.) siga leyendo `config('reputacion...')` sin enterarse del cambio.
     */
    private function aplicarConfiguracionPersistida(): void
    {
        try {
            $configuracion = ConfiguracionReputacion::query()->first();
        } catch (Throwable) {
            // Sin tabla todavía (instalación nueva antes de migrar): se queda con los defaults.
            return;
        }

        if ($configuracion === null) {
            return;
        }

        config(['reputacion' => array_replace_recursive(
            (array) config('reputacion', []),
            $configuracion->haciaArrayDeConfig(),
        )]);
    }
}

<?php

namespace App\Providers;

use App\Services\Reputacion\CheatDetectionService;
use App\Services\Reputacion\ReputationService;
use Illuminate\Support\ServiceProvider;

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
}

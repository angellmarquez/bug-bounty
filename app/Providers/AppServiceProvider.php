<?php

namespace App\Providers;

use App\Services\Pgp\Contracts\PgpDriver;
use App\Services\Pgp\Drivers\FallbackPgpDriver;
use App\Services\Pgp\Drivers\GpgBinaryDriver;
use App\Services\Pgp\Exceptions\PgpDriverUnavailableException;
use App\Services\Pgp\PgpService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PgpDriver::class, function (): PgpDriver {
            $driver = config('pgp.driver', 'auto');
            $gpg = new GpgBinaryDriver(
                binary: (string) config('pgp.gpg.binary'),
                homedir: (string) config('pgp.gpg.home'),
                passphrase: (string) config('pgp.gpg.passphrase'),
                timeout: (int) config('pgp.gpg.timeout'),
            );

            if ($driver === 'auto') {
                $driver = $gpg->available() ? 'gpg' : 'fallback';
            }

            $resolved = match ($driver) {
                'gpg' => $gpg,
                'fallback' => new FallbackPgpDriver((string) config('pgp.fallback.store')),
                default => throw new RuntimeException("Driver PGP no soportado: [{$driver}]."),
            };

            if (! $resolved->available()) {
                throw new PgpDriverUnavailableException(sprintf(
                    'El driver PGP [%s] no está disponible. Revisa config/pgp.php.',
                    $resolved->name(),
                ));
            }

            return $resolved;
        });

        $this->app->singleton(PgpService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if (config('app.vite_hot_file')) {
            Vite::useHotFile((string) config('app.vite_hot_file'));
        }

        if (config('app.vite_hot_file')) {
            Vite::useHotFile((string) config('app.vite_hot_file'));
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

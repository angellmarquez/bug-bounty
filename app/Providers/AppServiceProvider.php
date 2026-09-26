<?php

namespace App\Providers;

use App\Models\Adjunto;
use App\Models\Apelacion;
use App\Models\ClavePgpPlataforma;
use App\Models\Empresa;
use App\Models\ObjetivoPrograma;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\Sancion;
use App\Models\User;
use App\Services\Pgp\Contracts\PgpDriver;
use App\Services\Pgp\Drivers\FallbackPgpDriver;
use App\Services\Pgp\Drivers\GpgBinaryDriver;
use App\Services\Pgp\Exceptions\PgpDriverUnavailableException;
use App\Services\Pgp\PgpService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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

        // Alias corto y estable para `entidad_type` en `auditorias` (y cualquier otra
        // relación polimórfica): sin esto, cada sitio que auditaba escribía su propio
        // string a mano ('empresa', 'user', 'clave_pgp_plataforma', el FQCN completo...)
        // y el filtro de /admin/auditoria nunca coincidía con lo guardado.
        Relation::morphMap([
            'Reporte' => Reporte::class,
            'Programa' => Programa::class,
            'ObjetivoPrograma' => ObjetivoPrograma::class,
            'Usuario' => User::class,
            'Sancion' => Sancion::class,
            'Apelacion' => Apelacion::class,
            'Empresa' => Empresa::class,
            'ClavePgp' => ClavePgpPlataforma::class,
            'Adjunto' => Adjunto::class,
        ]);

        // Freno HTTP al guardar/enviar informes: frena scripts que disparan cientos de peticiones.
        RateLimiter::for('reportes', fn (Request $request) => Limit::perMinute(
            (int) config('reportes.limites_envio.peticiones_por_minuto', 20),
        )->by('reportes|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Freno HTTP a interacciones frecuentes (comentarios en timeline, invitaciones): evita spam/saturación.
        RateLimiter::for('interacciones', fn (Request $request) => Limit::perMinute(30)
            ->by('interacciones|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

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

        // Contraseña robusta en todos los entornos (antes, fuera de producción valía "password").
        // En producción además más larga y comprobada contra filtraciones conocidas (haveibeenpwned).
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(12)->max(128)->mixedCase()->letters()->numbers()->symbols()->uncompromised()
            : Password::min(8)->max(128)->mixedCase()->letters()->numbers()->symbols(),
        );
    }
}

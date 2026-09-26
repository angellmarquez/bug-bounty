<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        // Dos accesos separados: las empresas entran por /empresa/login y el resto
        // (investigadores, moderadores, administración) por /login. Cada formulario
        // envía `portal`; una cuenta que llega por el acceso equivocado no entra.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = mb_strtolower(trim((string) $request->input(Fortify::username())));
            $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

            if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            $esEmpresa = $user->tieneRol('empresa');
            $portalEmpresa = $request->input('portal') === 'empresa';

            if ($portalEmpresa !== $esEmpresa) {
                Auditoria::registrar('auth.portal_incorrecto', $user, ['portal' => $portalEmpresa ? 'empresa' : 'plataforma'], $user->id);

                throw ValidationException::withMessages([
                    Fortify::username() => $esEmpresa
                        ? 'Esta es una cuenta de empresa: inicia sesión desde el acceso para empresas.'
                        : 'Esta cuenta no es de empresa: inicia sesión desde el acceso para investigadores.',
                ]);
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}

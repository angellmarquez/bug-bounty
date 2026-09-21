<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $this->mostrarMensajeFlashComoToast($request);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // Slugs de los roles del usuario: el menú lateral los usa en todas las páginas.
            'userRoles' => fn () => $request->user()?->roles()->pluck('slug')->all() ?? [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Los controladores redirigen con ->with('success'|'error', ...); la interfaz solo
     * escucha el flash "toast" de Inertia, así que se convierte aquí para que el
     * usuario vea el resultado de cada acción.
     */
    private function mostrarMensajeFlashComoToast(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        foreach (['success', 'error'] as $tipo) {
            $mensaje = $request->session()->pull($tipo);

            if (is_string($mensaje) && $mensaje !== '') {
                Inertia::flash('toast', ['type' => $tipo, 'message' => $mensaje]);

                return;
            }
        }
    }
}

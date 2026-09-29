<?php

namespace App\Http\Middleware;

use App\Http\Controllers\NotificacionController;
use App\Models\Rol;
use App\Models\User;
use App\Services\Adjuntos\AdjuntoService;
use App\Services\Reputacion\Rangos;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        // Los roles los usan el menú y la tarjeta de la cuenta: una sola consulta por petición.
        $roles = null;
        $rolesDelUsuario = function () use ($request, &$roles) {
            return $roles ??= $request->user()?->roles()->get(['roles.slug', 'roles.nombre']) ?? collect();
        };

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // Slugs de los roles del usuario: el menú lateral los usa en todas las páginas.
            'userRoles' => fn () => $rolesDelUsuario()->pluck('slug')->all(),
            // Rangos de reputación y niveles de acceso: la interfaz calcula el rango de cualquier puntaje.
            'reputacionConfig' => fn () => app(Rangos::class)->paraInterfaz(),
            // Si se pueden adjuntar fotos nuevas (ADJUNTOS_HABILITADOS): si no, se oculta el selector.
            'fotosHabilitadas' => AdjuntoService::habilitados(),
            // Qué es esta cuenta hoy: roles, rango, suspensión, alcance de moderador y empresa.
            'cuenta' => fn () => $this->estadoCuenta($request->user(), $rolesDelUsuario()),
            'notificaciones' => fn () => $this->resumenNotificaciones($request->user()),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Límites de las fotos de evidencia: el selector de fotos los valida antes de subir.
            'limitesFotos' => [
                'max' => (int) config('adjuntos.max_por_entidad'),
                'max_kb' => AdjuntoService::maxKbEfectivo(),
                'max_total_kb' => AdjuntoService::maxTotalKb(),
                'mimes' => config('adjuntos.mimes'),
            ],
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

    /**
     * Resumen del estado de la cuenta para la insignia y la tarjeta "Mi estado".
     *
     * @param  Collection<int, Rol>  $rolesDelUsuario
     * @return array<string, mixed>|null
     */
    private function estadoCuenta(?User $usuario, Collection $rolesDelUsuario): ?array
    {
        if ($usuario === null) {
            return null;
        }

        $orden = ['administrador', 'moderador', 'empresa', 'investigador'];
        $roles = $rolesDelUsuario
            ->sortBy(fn ($rol): int => (int) array_search($rol->slug, $orden, true))
            ->map(fn ($rol): array => ['slug' => $rol->slug, 'nombre' => $rol->nombre])
            ->values()
            ->all();
        $slugs = array_column($roles, 'slug');

        $suspension = $usuario->suspensionActiva();
        $empresa = $usuario->empresas()->wherePivot('estado', 'activo')->latest('empresas.created_at')->first();

        return [
            'roles' => $roles,
            'reputacion' => (int) $usuario->reputation_score,
            'rango' => app(Rangos::class)->deReputacion((int) $usuario->reputation_score),
            'moderador' => in_array('moderador', $slugs, true)
                ? ['programas' => $usuario->programasModerados()->orderBy('nombre')->get(['programas.id', 'programas.nombre'])
                    ->map(fn ($programa): array => ['id' => $programa->id, 'nombre' => $programa->nombre])->all()]
                : null,
            'suspension' => $suspension === null ? null : [
                'hasta' => $suspension->suspension_hasta?->toISOString(),
                'motivo' => $suspension->motivo,
                'gravedad' => $suspension->gravedad->value,
            ],
            'empresa' => $empresa === null ? null : [
                'id' => $empresa->id,
                'nombre' => $empresa->nombre_comercial ?? $empresa->razon_social,
                'estado' => $empresa->estado->value,
                'motivo' => $empresa->motivo_estado,
                'rol_interno' => data_get($empresa->pivot, 'rol_interno'),
            ],
            'invitaciones_pendientes' => DB::table('programa_invitados')
                ->where('investigador_id', $usuario->id)
                ->where('estado', 'pendiente')
                ->count(),
            'verificacion' => $usuario->progresoVerificacion($suspension !== null),
        ];
    }

    /**
     * Los avisos de la campana: cuántos hay sin leer y los últimos, para el desplegable.
     *
     * @return array{no_leidas: int, recientes: array<int, array<string, mixed>>}|null
     */
    private function resumenNotificaciones(?User $usuario): ?array
    {
        if ($usuario === null) {
            return null;
        }

        return [
            'no_leidas' => $usuario->unreadNotifications()->count(),
            'recientes' => $usuario->notifications()->latest()->limit(6)->get()
                ->map(fn ($aviso) => NotificacionController::formato($aviso))
                ->all(),
        ];
    }
}

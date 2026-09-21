<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * La campana de avisos. Cada usuario solo ve y toca los suyos.
 */
class NotificacionController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $consulta = $request->user()->notifications();

        if ($request->input('filtro') === 'no_leidas') {
            $consulta = $request->user()->unreadNotifications();
        }

        $avisos = $consulta->latest()->paginate(20)->withQueryString();
        $avisos->getCollection()->transform(fn (DatabaseNotification $aviso) => self::formato($aviso));

        return Inertia::render('notificaciones/Index', [
            'avisos' => $avisos,
            'filtro' => $request->input('filtro') === 'no_leidas' ? 'no_leidas' : 'todas',
        ]);
    }

    /** Marca el aviso como leído y lleva a lo que anuncia. */
    public function abrir(Request $request, string $id): RedirectResponse
    {
        $aviso = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $aviso->markAsRead();

        $url = (string) ($aviso->data['url'] ?? '');

        // Solo rutas internas: un aviso nunca debe sacar al usuario de la plataforma.
        return str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : redirect()->route('notificaciones.index');
    }

    public function leer(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return back();
    }

    public function leerTodas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Marcaste todos los avisos como leídos.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function formato(DatabaseNotification $aviso): array
    {
        return [
            'id' => $aviso->id,
            'tipo' => $aviso->data['tipo'] ?? 'general',
            'titulo' => $aviso->data['titulo'] ?? '',
            'mensaje' => $aviso->data['mensaje'] ?? '',
            'url' => $aviso->data['url'] ?? null,
            'leida' => $aviso->read_at !== null,
            'created_at' => $aviso->created_at?->toISOString(),
        ];
    }
}

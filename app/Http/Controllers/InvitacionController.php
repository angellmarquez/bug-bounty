<?php

namespace App\Http\Controllers;

use App\Models\EmpresaInvitacion;
use App\Services\Empresas\MembresiaEmpresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

/**
 * Las invitaciones a empresas vistas por quien las recibe: cada persona solo ve y responde las suyas.
 */
class InvitacionController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $invitaciones = EmpresaInvitacion::query()
            ->with(['empresa:id,razon_social,nombre_comercial,sitio_web', 'invitadoPor:id,name'])
            ->where('usuario_id', $request->user()->id)
            ->latest()
            ->limit(30)
            ->get();

        $actual = $request->user()->empresaActiva();

        return Inertia::render('invitaciones/Index', [
            'invitaciones' => $invitaciones->map(fn (EmpresaInvitacion $invitacion): array => [
                'id' => $invitacion->id,
                'estado' => $invitacion->estado,
                'vigente' => $invitacion->estado === 'pendiente' && $invitacion->expira_en->isFuture(),
                'expira_en' => $invitacion->expira_en->toISOString(),
                'respondida_en' => $invitacion->respondida_en?->toISOString(),
                'empresa' => [
                    'nombre' => $invitacion->empresa->nombre_comercial ?? $invitacion->empresa->razon_social,
                    'sitio_web' => $invitacion->empresa->sitio_web,
                ],
                'invitada_por' => $invitacion->invitadoPor?->name,
            ])->all(),
            'empresaActual' => $actual === null ? null : [
                'nombre' => $actual->nombre_comercial ?? $actual->razon_social,
                'rol_interno' => $actual->pivot->rol_interno,
            ],
        ]);
    }

    public function aceptar(Request $request, EmpresaInvitacion $invitacion, MembresiaEmpresa $membresia): RedirectResponse
    {
        abort_unless((int) $invitacion->usuario_id === (int) $request->user()->id, 403, 'Esta invitación no es tuya.');

        try {
            $membresia->aceptar($invitacion->load('empresa'), $request->user());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('invitaciones.index')->with('error', $e->getMessage());
        }

        $nombre = $invitacion->empresa->nombre_comercial ?? $invitacion->empresa->razon_social;

        return redirect()->route('programas.gestion')
            ->with('success', "Ahora formas parte de {$nombre}: puedes publicar y gestionar sus programas.");
    }

    public function rechazar(Request $request, EmpresaInvitacion $invitacion, MembresiaEmpresa $membresia): RedirectResponse
    {
        abort_unless((int) $invitacion->usuario_id === (int) $request->user()->id, 403, 'Esta invitación no es tuya.');

        try {
            $membresia->rechazar($invitacion, $request->user());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('invitaciones.index')->with('error', $e->getMessage());
        }

        return redirect()->route('invitaciones.index')->with('success', 'Rechazaste la invitación.');
    }
}

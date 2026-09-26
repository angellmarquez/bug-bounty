<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Programa;
use App\Services\Notificaciones\Notificador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Las invitaciones a programas privados vistas por el investigador que las recibe:
 * cada uno solo ve y responde las suyas.
 */
class InvitacionController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $invitacionesProgramas = DB::table('programa_invitados')
            ->join('programas', 'programas.id', '=', 'programa_invitados.programa_id')
            ->leftJoin('empresas', 'empresas.id', '=', 'programas.empresa_id')
            ->leftJoin('users as invitador', 'invitador.id', '=', 'programa_invitados.invitado_por')
            ->where('programa_invitados.investigador_id', $request->user()->id)
            ->select([
                'programa_invitados.id',
                'programa_invitados.programa_id',
                'programa_invitados.estado',
                'programa_invitados.created_at',
                'programas.nombre as programa_nombre',
                'programas.slug as programa_slug',
                'empresas.nombre_comercial as empresa_nombre',
                'empresas.razon_social as empresa_razon_social',
                'invitador.name as invitado_por_nombre',
            ])
            ->latest('programa_invitados.created_at')
            ->get();

        return Inertia::render('invitaciones/Index', [
            'invitacionesProgramas' => $invitacionesProgramas->map(fn ($inv): array => [
                'id' => $inv->id,
                'programa_id' => $inv->programa_id,
                'programa_nombre' => $inv->programa_nombre,
                'programa_slug' => $inv->programa_slug,
                'empresa_nombre' => $inv->empresa_nombre ?? $inv->empresa_razon_social,
                'invitado_por' => $inv->invitado_por_nombre,
                'estado' => $inv->estado,
                'created_at' => $inv->created_at,
            ])->all(),
        ]);
    }

    public function aceptarPrograma(Request $request, Programa $programa, Notificador $notificador): RedirectResponse
    {
        $this->responder($request, $programa, 'aceptada', $notificador);

        return redirect()->route('programas.show', $programa)->with('success', "Invitación aceptada. Ahora puedes acceder a «{$programa->nombre}» y enviar reportes.");
    }

    public function rechazarPrograma(Request $request, Programa $programa, Notificador $notificador): RedirectResponse
    {
        $this->responder($request, $programa, 'rechazada', $notificador);

        return redirect()->route('invitaciones.index')->with('success', "Has rechazado la invitación al programa «{$programa->nombre}».");
    }

    /**
     * Solo se responde una invitación pendiente: una rechazada o retirada por la
     * empresa no se puede "aceptar" después para colarse en el programa.
     */
    private function responder(Request $request, Programa $programa, string $estado, Notificador $notificador): void
    {
        $user = $request->user();

        $actualizadas = DB::table('programa_invitados')
            ->where('programa_id', $programa->id)
            ->where('investigador_id', $user->id)
            ->where('estado', 'pendiente')
            ->update(['estado' => $estado, 'updated_at' => now()]);

        abort_if($actualizadas === 0, 404, 'No tienes una invitación pendiente para este programa.');

        Auditoria::registrar($estado === 'aceptada' ? 'programas.invitacion_aceptada' : 'programas.invitacion_rechazada', $programa, ['investigador_id' => $user->id]);

        $notificador->invitacionProgramaRespondida($programa, $user, $estado === 'aceptada');
    }
}

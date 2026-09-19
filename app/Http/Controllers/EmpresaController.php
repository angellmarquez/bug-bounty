<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEmpresa;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EmpresaController extends Controller
{
    public function dashboard(Request $request): InertiaResponse
    {
        $empresa = $request->user()
            ->empresas()
            ->withPivot(['rol_interno', 'estado'])
            ->latest('empresas.created_at')
            ->first();

        abort_if($empresa === null, 403, 'Tu usuario no pertenece a una empresa.');

        return Inertia::render('empresa/Dashboard', [
            'empresa' => [
                ...$empresa->only([
                    'id', 'razon_social', 'nombre_comercial', 'identificador_fiscal', 'email', 'estado', 'motivo_estado',
                ]),
                'estado' => $empresa->estado->value,
                'rol_interno' => $empresa->pivot->rol_interno,
                'puedeOperar' => $empresa->estado === EstadoEmpresa::Aprobada,
                'programas' => $empresa->programas()->latest()->get(['id', 'nombre', 'estado']),
            ],
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\CertificadoDivulgacion;
use App\Models\Reporte;
use App\Services\Certificados\CertificadoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CertificadoController extends Controller
{
    /**
     * Muestra el certificado oficial de hallazgo para un reporte validado o resuelto.
     */
    public function show(Reporte $reporte, CertificadoService $service, Request $request): InertiaResponse
    {
        $user = $request->user();

        $esAutor = (int) $reporte->investigador_id === (int) $user->id;
        $esAdmin = $user->tieneRol('administrador');
        $esModerador = $user->tieneRol('moderador') && $user->puedeModerarPrograma($reporte->programa);
        $esEmpresa = $reporte->programa->empresa_id !== null && (int) $reporte->programa->empresa_id === (int) $user->idEmpresaActiva();

        if (! ($esAutor || $esAdmin || $esModerador || $esEmpresa)) {
            abort(403, 'No tienes permiso para ver el certificado de este informe.');
        }

        if (! $reporte->estaAprobado()) {
            abort(404, 'Este informe aún no ha sido validado ni cerrado como resuelto.');
        }

        $certificado = $service->obtenerOCrear($reporte, $user);

        return Inertia::render('reportes/Certificado', [
            'certificado' => [
                'id' => $certificado->id,
                'codigo' => $certificado->codigo,
                'huella' => $certificado->huella,
                'firma_pgp' => $certificado->firma_pgp,
                'clave_huella' => $certificado->clave_huella,
                'datos' => $certificado->datos,
                'emitido_en' => $certificado->created_at?->toISOString(),
            ],
            'reporteId' => $reporte->id,
            'urlVerificacion' => route('certificados.verificar', ['codigo' => $certificado->codigo]),
        ]);
    }

    /**
     * Portal público de validación de autenticidad e integridad del certificado.
     * Accesible sin autenticación por cualquier persona o jurado evaluador.
     */
    public function verificar(string $codigo, CertificadoService $service): InertiaResponse
    {
        /** @var CertificadoDivulgacion|null $certificado */
        $certificado = CertificadoDivulgacion::query()->where('codigo', $codigo)->first();

        if ($certificado === null) {
            return Inertia::render('VerificarCertificado', [
                'existe' => false,
                'codigo' => $codigo,
                'verificacion' => null,
                'certificado' => null,
            ]);
        }

        $verificacion = $service->verificar($certificado);

        return Inertia::render('VerificarCertificado', [
            'existe' => true,
            'codigo' => $codigo,
            'verificacion' => $verificacion,
            'certificado' => [
                'codigo' => $certificado->codigo,
                'huella' => $certificado->huella,
                'firma_pgp' => $certificado->firma_pgp,
                'clave_huella' => $certificado->clave_huella,
                'datos' => $certificado->datos,
                'emitido_en' => $certificado->created_at?->toISOString(),
            ],
        ]);
    }
}

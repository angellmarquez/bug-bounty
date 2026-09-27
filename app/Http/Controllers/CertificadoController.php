<?php

namespace App\Http\Controllers;

use App\Abac\AccionesAbac;
use App\Models\CertificadoDivulgacion;
use App\Models\Reporte;
use App\Services\Certificados\CertificadoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CertificadoController extends Controller
{
    /**
     * Muestra el certificado oficial de un informe cerrado como resuelto.
     * Normalmente ya se emitió al cerrarlo; si no (informes cerrados antes), se emite ahora.
     */
    public function show(Reporte $reporte, CertificadoService $service, Request $request): InertiaResponse
    {
        // Sin cerrar aún: 404 solo para quien ya puede ver el informe; al resto, 403, para no
        // revelar en qué punto del flujo está un informe ajeno.
        if (! $reporte->admiteCertificado()) {
            abort_unless(Gate::allows('abac', [AccionesAbac::ReporteVer, $reporte]), 403, 'No tienes permiso para ver el certificado de este informe.');
            abort(404, 'Este informe aún no está cerrado como resuelto.');
        }

        Gate::authorize('abac', [AccionesAbac::CertificadoVer, $reporte]);

        $certificado = $service->obtenerOCrear($reporte, $request->user());

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
        $certificado = $this->buscar($codigo);

        if ($certificado === null) {
            return Inertia::render('VerificarCertificado', [
                'existe' => false,
                'codigo' => $codigo,
                'verificacion' => null,
                'certificado' => null,
                'descargas' => null,
            ]);
        }

        return Inertia::render('VerificarCertificado', [
            'existe' => true,
            'codigo' => $codigo,
            'verificacion' => $service->verificar($certificado),
            'certificado' => [
                'codigo' => $certificado->codigo,
                'huella' => $certificado->huella,
                'firma_pgp' => $certificado->firma_pgp,
                'clave_huella' => $certificado->clave_huella,
                'datos' => $certificado->datos,
                'emitido_en' => $certificado->created_at?->toISOString(),
            ],
            'descargas' => [
                'firma' => route('certificados.firma', ['codigo' => $certificado->codigo]),
                'clave' => $service->clavePublicaDe($certificado) !== null
                    ? route('certificados.clave', ['codigo' => $certificado->codigo])
                    : null,
            ],
        ]);
    }

    /**
     * Firma separada (detached) del certificado: lo firmado es su huella SHA-256 tal cual.
     */
    public function firma(string $codigo): Response
    {
        $certificado = $this->buscar($codigo) ?? abort(404);

        return $this->armored($certificado->firma_pgp, "{$certificado->codigo}.sig.asc");
    }

    /**
     * Clave pública de la plataforma que firmó el certificado (nunca la privada).
     */
    public function clave(string $codigo, CertificadoService $service): Response
    {
        $certificado = $this->buscar($codigo) ?? abort(404);
        $clave = $service->clavePublicaDe($certificado) ?? abort(404);

        return $this->armored($clave->clave_publica, "huella-{$clave->huella}.asc");
    }

    private function buscar(string $codigo): ?CertificadoDivulgacion
    {
        return CertificadoDivulgacion::query()->with('reporte')->where('codigo', $codigo)->first();
    }

    private function armored(string $contenido, string $archivo): Response
    {
        return response($contenido, 200, [
            'Content-Type' => 'application/pgp-keys; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$archivo}\"",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

<?php

namespace App\Services\Certificados;

use App\Models\CertificadoDivulgacion;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Pgp\PgpService;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Servicio encargado de emitir y verificar criptográficamente los
 * Certificados de Divulgación Responsable para informes aprobados.
 */
class CertificadoService
{
    public function __construct(
        protected PgpService $pgp,
    ) {}

    /**
     * Obtiene el certificado existente o emite uno nuevo sellado con SHA-256 y firma PGP.
     *
     * @throws InvalidArgumentException si el informe no está en un estado aprobado.
     */
    public function obtenerOCrear(Reporte $reporte, ?User $actor = null): CertificadoDivulgacion
    {
        if (! $reporte->estaAprobado()) {
            throw new InvalidArgumentException('Solo se pueden emitir certificados para informes validados o resueltos.');
        }

        $existente = $reporte->certificado;
        if ($existente !== null) {
            return $existente;
        }

        $reporte->loadMissing(['programa.empresa', 'investigador']);

        $empresa = $reporte->programa->empresa;
        $empresaNombre = $empresa !== null
            ? ($empresa->nombre_comercial ?? $empresa->razon_social)
            : 'Plataforma Bug Bounty';

        $datos = [
            'numero_reporte' => $reporte->numero_reporte,
            'titulo' => $reporte->titulo,
            'categoria' => $reporte->categoria ?? 'Vulnerabilidad de Seguridad',
            'severidad' => $reporte->severidad->value,
            'cvss_score' => (float) ($reporte->puntuacion_cvss ?? 0.0),
            'cvss_vector' => $reporte->vector_cvss,
            'programa_nombre' => $reporte->programa->nombre,
            'empresa_nombre' => $empresaNombre,
            'investigador_alias' => $reporte->investigador->name,
            'investigador_id' => $reporte->investigador_id,
            'fecha_reporte' => $reporte->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'fecha_resolucion' => $reporte->cerrado_en?->toIso8601String()
                ?? $reporte->updated_at?->toIso8601String()
                ?? now()->toIso8601String(),
        ];

        $codigo = 'BB-CERT-'.strtoupper(Str::random(10));
        $huella = $this->calcularHuellaCanonico($codigo, $reporte->id, $datos);

        $clavePlataforma = $this->pgp->asegurarClave();
        $firmaPgp = $this->pgp->sign($huella);

        return CertificadoDivulgacion::query()->create([
            'reporte_id' => $reporte->id,
            'codigo' => $codigo,
            'huella' => $huella,
            'firma_pgp' => $firmaPgp,
            'clave_huella' => $clavePlataforma->huella,
            'datos' => $datos,
            'emitido_por_id' => $actor?->id,
        ]);
    }

    /**
     * Verifica la autenticidad e integridad del certificado:
     * 1. Recalcula el SHA-256 de los datos canónicos.
     * 2. Verifica la firma digital PGP contra la clave de la plataforma.
     *
     * @return array{
     *     valido: bool,
     *     hash_valido: bool,
     *     firma_valida: bool,
     *     huella: string,
     *     huella_calculada: string,
     *     clave_huella: string|null,
     * }
     */
    public function verificar(CertificadoDivulgacion $certificado): array
    {
        $huellaEsperada = $this->calcularHuellaCanonico(
            $certificado->codigo,
            $certificado->reporte_id,
            $certificado->datos,
        );

        $hashValido = hash_equals($huellaEsperada, $certificado->huella);
        $firmaValida = $this->pgp->verify($certificado->huella, $certificado->firma_pgp);

        return [
            'valido' => $hashValido && $firmaValida,
            'hash_valido' => $hashValido,
            'firma_valida' => $firmaValida,
            'huella' => $certificado->huella,
            'huella_calculada' => $huellaEsperada,
            'clave_huella' => $certificado->clave_huella,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function calcularHuellaCanonico(string $codigo, int $reporteId, array $datos): string
    {
        $payload = json_encode([
            'codigo' => $codigo,
            'reporte_id' => $reporteId,
            'numero_reporte' => (string) ($datos['numero_reporte'] ?? ''),
            'severidad' => (string) ($datos['severidad'] ?? ''),
            'cvss_score' => (float) ($datos['cvss_score'] ?? 0.0),
            'cvss_vector' => $datos['cvss_vector'] ?? null,
            'programa' => (string) ($datos['programa_nombre'] ?? ''),
            'empresa' => (string) ($datos['empresa_nombre'] ?? ''),
            'investigador_id' => (int) ($datos['investigador_id'] ?? 0),
            'fecha_resolucion' => (string) ($datos['fecha_resolucion'] ?? ''),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $payload);
    }
}

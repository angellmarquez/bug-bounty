<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\ObjetivoPrograma;
use App\Models\Programa;
use App\Models\Reporte;
use App\Services\Pgp\PgpService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Genera la clave PGP de cada empresa que todavía no tenga una y re-cifra
 * todo lo que hasta ahora estaba cifrado solo a la clave de custodia
 * (reportes, programas, objetivos) para que también quede cifrado a la
 * clave de la empresa dueña.
 *
 * Se corre a mano una sola vez, al pasar del esquema de clave única al de
 * clave por empresa (no va en el arranque del contenedor). Re-ejecutarlo es
 * seguro -- vuelve a cifrar todo con las mismas claves, el resultado es
 * equivalente -- pero no se salta filas: cuesta lo mismo cada vez.
 */
class PgpMigrarClavesPorEmpresaCommand extends Command
{
    protected $signature = 'pgp:migrar-claves-por-empresa {--dry-run : No escribe nada, solo informa qué haría}';

    protected $description = 'Genera la clave PGP de cada empresa y re-cifra reportes/programas/objetivos existentes a [empresa, custodia]';

    public function handle(PgpService $pgp): int
    {
        if (! $pgp->available()) {
            $this->error("El driver PGP [{$pgp->driverName()}] no está disponible en este entorno.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $errores = 0;

        $empresas = Empresa::query()->whereHas('programas')->get();
        $this->info("Empresas con programas: {$empresas->count()}.");

        foreach ($empresas as $empresa) {
            try {
                if (! $dryRun) {
                    $clave = $pgp->claveDeEmpresa($empresa);
                    $this->line("  Empresa #{$empresa->id} ({$empresa->razon_social}): clave {$clave->huella}.");
                } else {
                    $this->line("  Empresa #{$empresa->id} ({$empresa->razon_social}): se generaría/usaría su clave.");
                }
            } catch (Throwable $e) {
                $this->error("  Empresa #{$empresa->id}: no se pudo generar su clave: {$e->getMessage()}");
                $errores++;
            }
        }

        $programas = Programa::withTrashed()->with('empresa')->get();
        $this->info("Programas a re-cifrar: {$programas->count()}.");

        foreach ($programas as $programa) {
            try {
                $this->reCifrarPrograma($pgp, $programa, $dryRun);
            } catch (Throwable $e) {
                $this->error("  Programa #{$programa->id}: {$e->getMessage()}");
                $errores++;
            }
        }

        $reportes = Reporte::withTrashed()->with('programa.empresa')->get();
        $this->info("Reportes a re-cifrar: {$reportes->count()}.");

        foreach ($reportes as $reporte) {
            try {
                $this->reCifrarReporte($pgp, $reporte, $dryRun);
            } catch (Throwable $e) {
                $this->error("  Reporte #{$reporte->id}: {$e->getMessage()}");
                $errores++;
            }
        }

        $this->info($dryRun ? 'Simulación terminada (--dry-run): no se escribió nada.' : 'Migración terminada.');

        if ($errores > 0) {
            $this->warn("{$errores} filas fallaron: revisá los mensajes de arriba.");
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reCifrarPrograma(PgpService $pgp, Programa $programa, bool $dryRun): void
    {
        $descifrado = $pgp->descifrarPrograma($programa->descripcion, $programa->bugs_buscados);

        if ($dryRun) {
            $this->line("  Programa #{$programa->id}: se re-cifraría (empresa ".($programa->empresa_id ?? 'ninguna').').');

            return;
        }

        $cifrado = $pgp->cifrarPrograma($descifrado['descripcion'], $descifrado['bugs_buscados'], $programa->empresa);
        $programa->forceFill([
            'descripcion' => $cifrado['descripcion'],
            'bugs_buscados' => $cifrado['bugs_buscados'],
        ])->save();

        foreach (ObjetivoPrograma::where('programa_id', $programa->id)->get() as $objetivo) {
            $objetivoDescifrado = $pgp->descifrarObjetivo($objetivo->valor, $objetivo->descripcion);
            $objetivoCifrado = $pgp->cifrarObjetivo($objetivoDescifrado['valor'], $objetivoDescifrado['descripcion'], $programa->empresa);
            $objetivo->forceFill($objetivoCifrado)->save();
        }

        $this->line("  Programa #{$programa->id}: re-cifrado.");
    }

    private function reCifrarReporte(PgpService $pgp, Reporte $reporte, bool $dryRun): void
    {
        $descifrado = $pgp->descifrarReporte((string) $reporte->descripcion, $reporte->poc);

        if ($dryRun) {
            $this->line("  Reporte #{$reporte->id}: se re-cifraría (empresa ".($reporte->programa->empresa_id ?? 'ninguna').').');

            return;
        }

        $cifrado = $pgp->cifrarReporte($descifrado['descripcion'], $descifrado['poc'] ?? [], $reporte->programa->empresa);
        $reporte->forceFill([
            'descripcion' => $cifrado['descripcion'],
            'poc' => $cifrado['poc'],
            'clave_huella' => $cifrado['clave_huella'],
        ])->save();

        $this->line("  Reporte #{$reporte->id}: re-cifrado.");
    }
}

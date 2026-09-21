<?php

namespace App\Console\Commands;

use App\Services\Pgp\PgpService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Genera e instala el par de claves PGP interno de la plataforma.
 *
 * Normalmente no hace falta ejecutarlo: la plataforma crea la clave sola (al desplegar o al
 * recibir el primer informe). Sirve para el equipo técnico y para regenerarla con --force.
 */
class PgpSetupCommand extends Command
{
    protected $signature = 'pgp:setup
        {--force : Regenera el par de claves aunque ya exista uno activo}
        {--identity= : Identidad usada al generar la clave (por defecto, la configurada más una marca única)}
        {--tolerante : No falla si no se puede crear ahora: la clave se creará sola al recibir el primer informe}';

    protected $description = 'Genera el par de claves PGP de la plataforma y lo persiste como activo';

    public function handle(PgpService $pgp): int
    {
        if (! $pgp->available()) {
            $this->error("El driver PGP [{$pgp->driverName()}] no está disponible en este entorno.");

            return $this->option('tolerante') ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && $pgp->platformKey() !== null) {
            $clave = $pgp->platformKey();
            $this->info("Ya existe una clave PGP activa [huella: {$clave->huella}].");
            $this->line('Usa --force para regenerarla (las claves antiguas se desactivan).');

            return self::SUCCESS;
        }

        $identity = (string) ($this->option('identity') ?: $pgp->identidadUnica());

        try {
            $clave = $pgp->crearClave('consola', $identity);
        } catch (Throwable $e) {
            $this->error(sprintf('No se pudo generar la clave PGP: %s', $e->getMessage()));

            if ($this->option('tolerante')) {
                $this->line('Se creará sola al recibir el primer informe.');

                return self::SUCCESS;
            }

            return self::FAILURE;
        }

        $this->info('Par de claves PGP generado y persistido como activo.');
        $this->line("   Huella:   {$clave->huella}");
        $this->line("   Identidad: {$clave->identidad}");
        $this->line("   Algoritmo: {$clave->algoritmo} ({$clave->bits} bits)");
        $this->line('   Expira:   '.($clave->expira_en?->toDateTimeString() ?? 'sin expiración'));

        if ($pgp->usesFallback()) {
            $this->warn('Driver de respaldo activo: la clave es FAKE (sin confidencialidad real). Solo local/testing.');
        }

        return self::SUCCESS;
    }
}

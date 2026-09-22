<?php

namespace App\Console\Commands;

use App\Services\Pgp\PgpService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Reimporta la clave activa de la plataforma (guardada en la base de datos)
 * en el keyring local del driver PGP.
 *
 * Pensado para correr en cada arranque del contenedor cuando el disco es
 * efímero (por ejemplo, un plan de Render sin disco persistente): el
 * keyring de GnuPG en disco se pierde en cada reinicio, pero la clave sigue
 * viva en la base de datos. Sin este paso, cifrar o descifrar fallaría con
 * "no se encontró la clave" aunque la fila siga activa.
 */
class PgpRestoreCommand extends Command
{
    protected $signature = 'pgp:restore';

    protected $description = 'Reimporta la clave activa de la plataforma en el keyring local del driver PGP';

    public function handle(PgpService $pgp): int
    {
        if (! $pgp->available()) {
            $this->warn("El driver PGP [{$pgp->driverName()}] no está disponible; nada que restaurar.");

            return self::SUCCESS;
        }

        try {
            $restaurada = $pgp->restaurarEnKeyring();
        } catch (Throwable $e) {
            $this->error(sprintf('No se pudo reimportar la clave de la plataforma: %s', $e->getMessage()));

            return self::FAILURE;
        }

        if (! $restaurada) {
            $this->line('No hay clave PGP activa en la base de datos; nada que restaurar.');

            return self::SUCCESS;
        }

        $this->info('Clave de la plataforma reimportada en el keyring local.');

        return self::SUCCESS;
    }
}

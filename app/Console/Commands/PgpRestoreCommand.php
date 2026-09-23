<?php

namespace App\Console\Commands;

use App\Services\Pgp\PgpService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Reimporta la clave de custodia y la de cada empresa (guardadas en la base
 * de datos) en el keyring local del driver PGP.
 *
 * Pensado para correr en cada arranque del contenedor cuando el disco es
 * efímero (por ejemplo, un plan de Render sin disco persistente): el
 * keyring de GnuPG en disco se pierde en cada reinicio, pero las claves
 * siguen vivas en la base de datos. Sin este paso, cifrar o descifrar
 * fallaría con "no se encontró la clave" aunque las filas sigan activas.
 */
class PgpRestoreCommand extends Command
{
    protected $signature = 'pgp:restore';

    protected $description = 'Reimporta la clave de custodia y las de cada empresa en el keyring local del driver PGP';

    public function handle(PgpService $pgp): int
    {
        if (! $pgp->available()) {
            $this->warn("El driver PGP [{$pgp->driverName()}] no está disponible; nada que restaurar.");

            return self::SUCCESS;
        }

        try {
            $restaurada = $pgp->restaurarEnKeyring();
            $empresas = $pgp->restaurarClavesDeEmpresaEnKeyring();
        } catch (Throwable $e) {
            $this->error(sprintf('No se pudo reimportar el keyring PGP: %s', $e->getMessage()));

            return self::FAILURE;
        }

        if (! $restaurada) {
            $this->line('No hay clave de custodia activa en la base de datos; nada que restaurar.');
        } else {
            $this->info('Clave de custodia reimportada en el keyring local.');
        }

        $this->info("Claves de empresa reimportadas: {$empresas}.");

        return self::SUCCESS;
    }
}

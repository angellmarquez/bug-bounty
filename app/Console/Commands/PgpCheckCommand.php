<?php

namespace App\Console\Commands;

use App\Services\Pgp\Exceptions\PgpException;
use App\Services\Pgp\PgpService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Comprueba el estado de la infraestructura PGP de la plataforma.
 */
class PgpCheckCommand extends Command
{
    protected $signature = 'pgp:check';

    protected $description = 'Verifica el driver PGP, la clave de la plataforma y un ciclo de cifrado/descifrado';

    public function handle(PgpService $pgp): int
    {
        $this->line('=== Estado de la infraestructura PGP ===');

        $driverName = $pgp->driverName();
        $available = $pgp->available();
        $this->line(sprintf('%sDriver PGP:  %s (%s)', $this->prefix($available), $driverName, $available ? 'disponible' : 'NO disponible'));

        if (! $available) {
            $this->error('No se puede operar sin un driver PGP disponible.');

            return self::FAILURE;
        }

        if ($pgp->usesFallback()) {
            $this->warn('Driver de respaldo: NO proporciona cifrado real. Solo debe usarse en local/testing.');

            if (app()->isProduction()) {
                $this->error('Es producción: el driver de respaldo está prohibido.');

                return self::FAILURE;
            }
        } else {
            $this->line('Driver de binario GnuPG (cifrado real).');
        }

        $clave = $pgp->platformKey();

        if ($clave === null) {
            $this->error('No hay clave PGP activa de la plataforma. Ejecuta: php artisan pgp:setup');

            return self::FAILURE;
        }

        $this->line(sprintf('%sClave activa: %s [%s, %d bits]', $this->prefix(true), $clave->huella, $clave->algoritmo ?? '?', $clave->bits ?? 0));

        if ($clave->expira_en !== null) {
            if ($clave->expira_en->isPast()) {
                $this->error('La clave activa está expirada. Regenera con: php artisan pgp:setup --force');
            } else {
                $this->line(sprintf('   Expiración: %s (%s)', $clave->expira_en->toDateTimeString(), $clave->expira_en->diffForHumans()));
            }
        } else {
            $this->line('   Expiración: sin expiración');
        }

        $this->line('Ciclo cifrado/descifrado con la clave de la plataforma...');

        try {
            $mensaje = 'prueba-pgp-'.mt_rand();
            $cifrado = $pgp->encrypt($mensaje, $clave->huella);
            $descifrado = $pgp->decrypt($cifrado);
        } catch (PgpException $e) {
            $this->error(sprintf('Fallo en el ciclo de cifrado/descifrado: %s', $e->getMessage()));

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error(sprintf('Error inesperado en el ciclo PGP: %s', $e->getMessage()));

            return self::FAILURE;
        }

        if (hash_equals($mensaje, $descifrado)) {
            $this->info('Ciclo de cifrado/descifrado: OK');
        } else {
            $this->error('El ciclo de cifrado/descifrado devolvió un contenido distinto.');

            return self::FAILURE;
        }

        $this->info('Infraestructura PGP: OK');

        return self::SUCCESS;
    }

    private function prefix(bool $ok): string
    {
        return $ok ? '[OK] ' : '[!!] ';
    }
}

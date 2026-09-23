<?php

namespace Database\Seeders;

use App\Services\Pgp\PgpService;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Deja lista la clave de cifrado de la plataforma. Es idempotente y no es fatal si falla:
 * en ese caso se creará sola al recibir el primer informe.
 */
class ClavePgpSeeder extends Seeder
{
    public function run(): void
    {
        try {
            $clave = app(PgpService::class)->asegurarClave('instalacion');
            $this->command->info("Clave de cifrado lista (huella {$clave->huella}).");
        } catch (Throwable $e) {
            $this->command->warn("No se pudo crear la clave de cifrado ahora: {$e->getMessage()} Se creará sola al recibir el primer informe.");
        }
    }
}

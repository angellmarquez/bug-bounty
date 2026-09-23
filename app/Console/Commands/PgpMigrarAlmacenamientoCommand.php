<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Migra `clave_privada` (en claves_pgp_plataforma y claves_pgp_empresa) de
 * estar cifrada con APP_KEY (el cast `encrypted` de Laravel, de antes) a
 * estar cifrada con el secreto dedicado PGP_STORAGE_KEY.
 *
 * Seguro de re-correr: por cada fila, si ya descifra con la clave nueva la
 * deja tal cual; si no, la migra desde la vieja. Opera con SQL crudo (no
 * pasa por Eloquent) justo para no depender de qué cast esté activo en el
 * modelo en este momento.
 */
class PgpMigrarAlmacenamientoCommand extends Command
{
    protected $signature = 'pgp:migrar-almacenamiento';

    protected $description = 'Migra clave_privada de APP_KEY al secreto dedicado PGP_STORAGE_KEY';

    public function handle(): int
    {
        $storageKey = (string) config('pgp.storage_key');

        if ($storageKey === '') {
            $this->error('Falta PGP_STORAGE_KEY en el .env: no hay a qué secreto migrar.');

            return self::FAILURE;
        }

        $migradas = 0;
        $yaMigradas = 0;
        $fallidas = 0;

        foreach (['claves_pgp_plataforma', 'claves_pgp_empresa'] as $tabla) {
            if (! $this->tablaExiste($tabla)) {
                continue;
            }

            foreach (DB::table($tabla)->select('id', 'clave_privada')->get() as $fila) {
                $resultado = $this->migrarFila($tabla, $fila->id, (string) $fila->clave_privada, $storageKey);

                match ($resultado) {
                    'migrada' => $migradas++,
                    'ya_migrada' => $yaMigradas++,
                    default => $fallidas++,
                };
            }
        }

        $this->info("Migradas: {$migradas}. Ya estaban migradas: {$yaMigradas}. Fallidas: {$fallidas}.");

        return $fallidas > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function tablaExiste(string $tabla): bool
    {
        return Schema::hasTable($tabla);
    }

    private function migrarFila(string $tabla, int $id, string $valorActual, string $storageKey): string
    {
        if ($valorActual === '') {
            return 'ya_migrada';
        }

        // ¿Ya descifra con la clave nueva? No hay nada que hacer.
        try {
            $this->descifrarCon($storageKey, $valorActual);

            return 'ya_migrada';
        } catch (DecryptException) {
            // Sigue con la clave vieja.
        }

        try {
            $plano = Crypt::decryptString($valorActual);
        } catch (DecryptException $e) {
            $this->error("[{$tabla}#{$id}] No descifra ni con la clave nueva ni con APP_KEY: {$e->getMessage()}");

            return 'fallida';
        }

        $nuevoValor = $this->cifrarCon($storageKey, $plano);

        DB::table($tabla)->where('id', $id)->update(['clave_privada' => $nuevoValor]);
        $this->line("[{$tabla}#{$id}] migrada.");

        return 'migrada';
    }

    private function descifrarCon(string $key, string $valor): string
    {
        return $this->encrypter($key)->decryptString($valor);
    }

    private function cifrarCon(string $key, string $valor): string
    {
        return $this->encrypter($key)->encryptString($valor);
    }

    private function encrypter(string $key): Encrypter
    {
        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }

        if ($key === '') {
            throw new RuntimeException('PGP_STORAGE_KEY vacío o inválido.');
        }

        return new Encrypter($key, 'aes-256-cbc');
    }
}

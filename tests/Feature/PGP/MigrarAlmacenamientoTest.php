<?php

use Illuminate\Support\Facades\DB;

/** Inserta una clave de plataforma cuya clave privada no se puede leer con ningún secreto. */
function claveIlegible(bool $activa): void
{
    if ($activa) {
        DB::table('claves_pgp_plataforma')->update(['activa' => false]);
    }

    DB::table('claves_pgp_plataforma')->insert([
        'huella' => strtoupper(bin2hex(random_bytes(20))),
        'clave_publica' => 'publica',
        'clave_privada' => 'cifrada-con-un-secreto-que-ya-no-existe',
        'identidad' => 'Clave antigua <seguridad@localhost>',
        'activa' => $activa,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('una clave inactiva ilegible no impide arrancar: solo se avisa', function () {
    claveIlegible(activa: false);

    $this->artisan('pgp:migrar-almacenamiento')
        ->expectsOutputToContain('Clave inactiva ilegible')
        ->assertSuccessful();
});

test('si la clave activa no se puede leer, el comando falla', function () {
    claveIlegible(activa: true);

    $this->artisan('pgp:migrar-almacenamiento')->assertFailed();
});

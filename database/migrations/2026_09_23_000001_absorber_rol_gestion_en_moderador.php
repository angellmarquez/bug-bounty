<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El rol «Gestión» (triaje y operación de programas) quedó duplicado con el de
     * «Moderador»: lo absorbe. Quien lo tuviera pasa a moderador (sin programas
     * asignados hasta que un administrador se los dé) y el rol se elimina.
     */
    public function up(): void
    {
        $gestionId = DB::table('roles')->where('slug', 'gestion')->value('id');

        if ($gestionId === null) {
            return;
        }

        $moderadorId = DB::table('roles')->where('slug', 'moderador')->value('id')
            ?? DB::table('roles')->insertGetId([
                'nombre' => 'Moderador',
                'slug' => 'moderador',
                'descripcion' => 'Revisa y tría los informes de los programas asignados y resuelve apelaciones.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $usuarios = DB::table('rol_usuario')->where('rol_id', $gestionId)->pluck('usuario_id');

        foreach ($usuarios as $usuarioId) {
            $yaEsModerador = DB::table('rol_usuario')
                ->where('rol_id', $moderadorId)
                ->where('usuario_id', $usuarioId)
                ->exists();

            if (! $yaEsModerador) {
                DB::table('rol_usuario')->insert([
                    'rol_id' => $moderadorId,
                    'usuario_id' => $usuarioId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('rol_usuario')->where('rol_id', $gestionId)->delete();
        DB::table('roles')->where('id', $gestionId)->delete();
    }

    /** No se puede saber quién era Gestión: el rol simplemente deja de existir. */
    public function down(): void {}
};

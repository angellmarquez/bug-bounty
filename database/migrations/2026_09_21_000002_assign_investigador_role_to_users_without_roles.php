<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los usuarios registrados antes de que el registro asignara el rol
     * `investigador` no tienen ningún rol y ABAC les deniega todo (403 al
     * ver programas). Se les asigna el rol por defecto del registro.
     */
    public function up(): void
    {
        $sinRol = DB::table('users')
            ->whereNotIn('id', DB::table('rol_usuario')->select('usuario_id'))
            ->pluck('id');

        if ($sinRol->isEmpty()) {
            return;
        }

        $rolId = DB::table('roles')->where('slug', 'investigador')->value('id')
            ?? DB::table('roles')->insertGetId([
                'nombre' => 'Investigador',
                'slug' => 'investigador',
                'descripcion' => 'Rol por defecto para nuevos registros. Presenta reportes y gestiona su perfil PGP.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('rol_usuario')->insert(
            $sinRol->map(fn ($id) => [
                'rol_id' => $rolId,
                'usuario_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all(),
        );
    }

    public function down(): void
    {
        // Irreversible: no se puede distinguir qué asignaciones eran previas.
    }
};

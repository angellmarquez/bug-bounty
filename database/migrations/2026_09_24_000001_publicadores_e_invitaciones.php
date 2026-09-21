<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modelo de empresas: un propietario (quien la creó) y «publicadores», investigadores que
     * el propietario invita para publicar programas en su nombre. Un usuario pertenece a una
     * sola empresa. Las invitaciones van dirigidas a un usuario registrado (por su correo) y
     * se aceptan o rechazan desde la plataforma; ya no hay enlaces con token.
     */
    public function up(): void
    {
        $rolEmpresa = DB::table('roles')->where('slug', 'empresa')->value('id');
        $rolInvestigador = DB::table('roles')->where('slug', 'investigador')->value('id');
        $prohibidos = DB::table('roles')->whereIn('slug', ['moderador', 'administrador'])->pluck('id');

        // 1) Los antiguos «miembros» pasan a publicadores. Ya no llevan el rol global «empresa»
        //    (que es del propietario y da acceso a los informes) sino el de investigador.
        //    Un moderador o administrador no puede pertenecer a una empresa: se le retira.
        $miembros = DB::table('empresa_usuario')->where('rol_interno', 'miembro')->get();

        foreach ($miembros as $miembro) {
            $roles = DB::table('rol_usuario')->where('usuario_id', $miembro->usuario_id)->pluck('rol_id');

            if ($roles->intersect($prohibidos)->isNotEmpty()) {
                DB::table('empresa_usuario')->where('id', $miembro->id)->delete();

                continue;
            }

            DB::table('empresa_usuario')->where('id', $miembro->id)->update(['rol_interno' => 'publicador']);

            if ($rolEmpresa !== null) {
                DB::table('rol_usuario')->where('usuario_id', $miembro->usuario_id)->where('rol_id', $rolEmpresa)->delete();
            }

            if ($rolInvestigador !== null && ! $roles->contains($rolInvestigador)) {
                DB::table('rol_usuario')->insert([
                    'rol_id' => $rolInvestigador,
                    'usuario_id' => $miembro->usuario_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2) Una sola empresa por usuario: si alguien estaba en varias, se conserva la suya
        //    (como propietario) o, si no, la más antigua.
        $repetidos = DB::table('empresa_usuario')->select('usuario_id')->groupBy('usuario_id')->havingRaw('count(*) > 1')->pluck('usuario_id');

        foreach ($repetidos as $usuarioId) {
            $filas = DB::table('empresa_usuario')->where('usuario_id', $usuarioId)->orderBy('id')->get();
            $conservar = $filas->firstWhere('rol_interno', 'propietario') ?? $filas->first();
            DB::table('empresa_usuario')->where('usuario_id', $usuarioId)->where('id', '!=', $conservar->id)->delete();
        }

        Schema::table('empresa_usuario', function (Blueprint $table) {
            $table->unique('usuario_id');
        });

        // 3) Invitaciones: ahora se dirigen a un usuario y se responden desde la plataforma.
        Schema::table('empresa_invitaciones', function (Blueprint $table) {
            $table->foreignId('usuario_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('respondida_en')->nullable();
        });

        // Los enlaces con token de antes dejan de servir.
        DB::table('empresa_invitaciones')->where('estado', 'pendiente')->update(['estado' => 'cancelada']);
        DB::table('empresa_invitaciones')->where('rol_interno', 'miembro')->update(['rol_interno' => 'publicador']);
    }

    public function down(): void
    {
        Schema::table('empresa_invitaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usuario_id');
            $table->dropColumn('respondida_en');
        });

        Schema::table('empresa_usuario', function (Blueprint $table) {
            $table->dropUnique(['usuario_id']);
        });
    }
};

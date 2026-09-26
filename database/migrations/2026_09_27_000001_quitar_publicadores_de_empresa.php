<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un investigador no forma parte de ninguna empresa ni publica programas: la única
     * invitación que recibe es a un programa privado, para enviarle informes
     * (`programa_invitados`). Se retiran los «publicadores» y sus invitaciones.
     */
    public function up(): void
    {
        DB::table('empresa_usuario')->where('rol_interno', 'publicador')->delete();

        Schema::dropIfExists('empresa_invitaciones');
    }

    public function down(): void
    {
        Schema::create('empresa_invitaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->string('rol_interno')->default('publicador');
            $table->string('estado')->default('pendiente')->index();
            $table->foreignId('invitado_por')->constrained('users')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('expira_en');
            $table->timestamp('aceptado_en')->nullable();
            $table->timestamp('respondida_en')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'email', 'estado']);
        });
    }
};

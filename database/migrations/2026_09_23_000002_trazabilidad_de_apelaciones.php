<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trazabilidad de sanciones y apelaciones: quién aplicó la sanción (null = sistema, por la
     * auditoría automática) y un registro inmutable de cada paso de la apelación, con una
     * «huella» SHA-256 encadenada que permite comprobar que nadie lo alteró.
     */
    public function up(): void
    {
        Schema::table('sanciones', function (Blueprint $table) {
            $table->foreignId('aplicada_por')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('apelacion_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apelacion_id')->constrained('apelaciones')->cascadeOnDelete();
            $table->string('tipo');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // Copia de quién era el actor en ese momento: sigue siendo válida aunque cambie de nombre o de rol.
            $table->string('actor_nombre')->nullable();
            $table->string('actor_rol')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('nota')->nullable();
            $table->json('datos')->nullable();
            $table->string('huella_anterior', 64)->nullable();
            $table->string('huella', 64);
            $table->timestamp('created_at')->nullable();

            $table->index('apelacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apelacion_eventos');

        Schema::table('sanciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aplicada_por');
        });
    }
};

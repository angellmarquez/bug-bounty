<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La gestión de claves PGP es exclusiva de la plataforma
     * (claves_pgp_plataforma). Se elimina el registro de claves PGP de los
     * investigadores.
     */
    public function up(): void
    {
        Schema::dropIfExists('claves_pgp');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('claves_pgp', function ($table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('id_clave');
            $table->string('huella')->unique();
            $table->text('clave_publica');
            $table->string('algoritmo')->nullable();
            $table->unsignedInteger('bits')->nullable();
            $table->date('creada_en')->nullable();
            $table->timestamp('expira_en')->nullable();
            $table->string('estado')->default('pendiente_verificacion');
            $table->boolean('es_principal')->default(false);
            $table->timestamp('verificada_en')->nullable();
            $table->timestamp('ultimo_uso_en')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('usuario_id');
        });
    }
};

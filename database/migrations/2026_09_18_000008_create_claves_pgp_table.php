<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('claves_pgp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('id_clave');
            $table->string('huella')->unique();
            $table->text('clave_publica');
            $table->string('algoritmo')->nullable();
            $table->integer('bits')->nullable();
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claves_pgp');
    }
};

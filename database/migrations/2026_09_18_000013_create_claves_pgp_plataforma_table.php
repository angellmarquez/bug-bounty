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
        Schema::create('claves_pgp_plataforma', function (Blueprint $table) {
            $table->id();
            $table->string('id_clave')->nullable();
            $table->string('huella')->unique();
            $table->text('clave_publica');
            $table->text('clave_privada');
            $table->string('identidad');
            $table->string('algoritmo')->nullable();
            $table->integer('bits')->nullable();
            $table->date('creada_en')->nullable();
            $table->timestamp('expira_en')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index('activa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claves_pgp_plataforma');
    }
};

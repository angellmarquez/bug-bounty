<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una clave PGP por empresa (reemplaza la clave única de plataforma como
 * destinatario "de fondo": ahora cada empresa tiene la propia). Los reportes
 * y programas se cifran a [clave de la empresa, clave de custodia en
 * claves_pgp_plataforma] a la vez -- ver PgpService::cifrarReporte().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claves_pgp_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claves_pgp_empresa');
    }
};

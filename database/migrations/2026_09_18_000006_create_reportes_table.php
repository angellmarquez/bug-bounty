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
        Schema::create('reportes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_reporte')->unique();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->foreignId('investigador_id')->constrained('users');
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo');
            $table->text('descripcion');
            $table->string('categoria')->nullable();
            $table->string('vector_cvss')->nullable();
            $table->decimal('puntuacion_cvss', 4, 1)->nullable();
            $table->string('severidad')->nullable();
            $table->json('poc')->nullable();
            $table->string('estado')->default('borrador');
            $table->decimal('recompensa', 10, 2)->nullable();
            $table->string('moneda', 3)->default('USD');
            $table->foreignId('es_duplicado_de')->nullable()->constrained('reportes')->nullOnDelete();
            $table->text('notas_internas')->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('programa_id');
            $table->index('investigador_id');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reportes');
    }
};

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
        Schema::create('apelaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sancion_id')->constrained('sanciones')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->text('motivo');
            $table->json('evidencia')->nullable();
            $table->string('estado')->default('pendiente');
            $table->foreignId('resuelta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelta_en')->nullable();
            $table->text('nota_resolucion')->nullable();
            $table->timestamps();

            $table->index('sancion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apelaciones');
    }
};

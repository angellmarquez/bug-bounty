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
        Schema::create('ledger_reputacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->integer('puntos');
            $table->string('motivo');
            $table->foreignId('reporte_id')->nullable()->constrained('reportes')->nullOnDelete();
            $table->foreignId('sancion_id')->nullable()->constrained('sanciones')->nullOnDelete();
            $table->foreignId('apelacion_id')->nullable()->constrained('apelaciones')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('usuario_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_reputacion');
    }
};

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
        Schema::create('sanciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reporte_id')->nullable()->constrained('reportes')->nullOnDelete();
            $table->string('motivo');
            $table->string('gravedad');
            $table->integer('puntos');
            $table->string('estado')->default('aplicada');
            $table->timestamp('suspension_desde')->nullable();
            $table->timestamp('suspension_hasta')->nullable();
            $table->timestamp('plazo_apelacion')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('usuario_id');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sanciones');
    }
};

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
        Schema::create('programa_invitados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->foreignId('investigador_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitado_por')->constrained('users');
            $table->string('estado')->default('pendiente'); // pendiente, aceptada, rechazada, cancelada
            $table->timestamps();

            $table->unique(['programa_id', 'investigador_id']);
            $table->index(['programa_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programa_invitados');
    }
};

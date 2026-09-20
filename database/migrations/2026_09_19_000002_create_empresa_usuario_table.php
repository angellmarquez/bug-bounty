<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('rol_interno')->default('miembro');
            $table->string('estado')->default('activo');
            $table->timestamp('invitado_en')->nullable();
            $table->timestamp('aceptado_en')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'usuario_id']);
            $table->index(['usuario_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_usuario');
    }
};

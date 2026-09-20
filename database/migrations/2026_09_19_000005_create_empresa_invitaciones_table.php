<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_invitaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->string('rol_interno')->default('miembro');
            $table->string('estado')->default('pendiente')->index();
            $table->foreignId('invitado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expira_en');
            $table->timestamp('aceptado_en')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'email', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_invitaciones');
    }
};

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
        Schema::create('objetivos_programa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('valor');
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index('programa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('objetivos_programa');
    }
};

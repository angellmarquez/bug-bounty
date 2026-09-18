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
        Schema::create('programas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->text('descripcion');
            $table->string('estado')->default('borrador');
            $table->decimal('recompensa_min', 10, 2)->default(0);
            $table->decimal('recompensa_max', 10, 2)->default(0);
            $table->string('moneda', 3)->default('USD');
            $table->boolean('requiere_poc')->default(false);
            $table->boolean('es_publico')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inicia_en')->nullable();
            $table->timestamp('termina_en')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programas');
    }
};

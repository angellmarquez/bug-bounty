<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fotos de evidencia de informes y apelaciones. La imagen vive cifrada en el
     * disco de `config('adjuntos.disco')`; aquí solo quedan sus metadatos.
     */
    public function up(): void
    {
        Schema::create('adjuntos', function (Blueprint $table) {
            $table->id();
            $table->morphs('adjuntable');
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre_original');
            $table->string('mime', 50);
            $table->unsignedInteger('tamano');
            $table->unsignedSmallInteger('ancho');
            $table->unsignedSmallInteger('alto');
            // Huella de la imagen ya procesada (en claro): prueba que la evidencia no cambió.
            $table->char('sha256', 64);
            $table->string('disco', 50);
            $table->string('ruta')->unique();
            $table->string('clave_huella')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjuntos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila única con los parámetros del motor de reputación que el admin puede
 * ajustar desde /admin/config/reputacion. Antes de esta migración, guardar
 * ese formulario solo escribía un registro en `auditorias`: los valores de
 * `config/reputacion.php` nunca cambiaban de verdad (ver ReputacionServiceProvider,
 * que ahora carga esta fila encima de esos defaults al arrancar la app).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_reputacion', function (Blueprint $table) {
            $table->id();
            $table->integer('puntos_inicial');
            $table->integer('puntos_reporte_validado');
            $table->integer('puntos_reporte_resuelto');
            $table->integer('puntos_calidad_documentacion');
            $table->integer('puntos_participacion');
            $table->integer('penalizacion_leve');
            $table->integer('penalizacion_media');
            $table->integer('penalizacion_grave');
            $table->integer('suspension_leve_dias');
            $table->integer('suspension_media_dias');
            $table->integer('suspension_grave_dias');
            $table->integer('plazo_apelacion_dias');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_reputacion');
    }
};

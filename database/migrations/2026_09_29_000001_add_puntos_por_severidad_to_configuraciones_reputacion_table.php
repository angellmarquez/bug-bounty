<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla de puntos por severidad es la que de verdad reparte la reputación (un informe casi
 * siempre tiene severidad CVSS), pero no se podía editar desde /admin/config/reputacion: el
 * panel solo cambiaba los puntos "sin severidad", que casi nunca se usan. Null = los defaults
 * de config/reputacion.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_reputacion', function (Blueprint $table) {
            $table->json('puntos_por_severidad')->nullable()->after('puntos_participacion');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_reputacion', function (Blueprint $table) {
            $table->dropColumn('puntos_por_severidad');
        });
    }
};

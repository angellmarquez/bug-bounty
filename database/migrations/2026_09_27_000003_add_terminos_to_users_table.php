<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Constancia de que el usuario aceptó los Términos de Servicio y la Política de
     * Privacidad al registrarse: cuándo y qué versión (config('legal.version')).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terminos_aceptados_en')->nullable()->after('tema');
            $table->string('terminos_version', 20)->nullable()->after('terminos_aceptados_en');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terminos_aceptados_en', 'terminos_version']);
        });
    }
};

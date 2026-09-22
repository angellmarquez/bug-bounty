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
        // 'valor' pasa a text: cifrado con PGP, un bloque armored supera los 255
        // caracteres de un varchar aunque el dominio/URL original sea corto.
        Schema::table('objetivos_programa', function (Blueprint $table) {
            $table->text('valor')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('objetivos_programa', function (Blueprint $table) {
            $table->string('valor')->change();
        });
    }
};

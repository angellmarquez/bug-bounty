<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Todo programa exige PoC para comprobar los hallazgos (ver AGENTS.md): ya no
 * existe la opción de marcarla como opcional por programa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->dropColumn('requiere_poc');
        });
    }

    public function down(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->boolean('requiere_poc')->default(true)->after('estado');
        });
    }
};

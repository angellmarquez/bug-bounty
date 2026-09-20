<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A partir de ahora el PoC se custodia cifrado con PGP (texto armored)
     * en lugar de como JSON en claro. Se registra además la huella de la
     * clave de la plataforma usada para cifrar (trazabilidad).
     */
    public function up(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->text('poc')->nullable()->change();
            $table->string('clave_huella')->nullable()->after('poc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn('clave_huella');
            $table->json('poc')->nullable()->change();
        });
    }
};
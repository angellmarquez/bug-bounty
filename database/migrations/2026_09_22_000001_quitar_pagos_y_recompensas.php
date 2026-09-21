<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La plataforma ya no gestiona dinero: la recompensa es la reputación.
 *
 * Los informes que estaban en los estados de pago se reubican en el flujo nuevo
 * (validado → en reparación → cerrado) para que ninguno quede en un estado que ya no existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('reportes')->where('estado', 'pagado')->update(['estado' => 'cerrado']);
        DB::table('reportes')->where('estado', 'pago_pendiente')->update(['estado' => 'en_reparacion']);
        DB::table('eventos_reporte')->where('tipo', 'pago')->update(['tipo' => 'cambio_estado']);

        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn(['recompensa', 'moneda']);
        });

        Schema::table('programas', function (Blueprint $table) {
            $table->dropColumn(['recompensa_min', 'recompensa_max', 'moneda']);
        });
    }

    public function down(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->decimal('recompensa_min', 10, 2)->default(0);
            $table->decimal('recompensa_max', 10, 2)->default(0);
            $table->string('moneda', 3)->default('USD');
        });

        Schema::table('reportes', function (Blueprint $table) {
            $table->decimal('recompensa', 10, 2)->nullable();
            $table->string('moneda', 3)->default('USD');
        });
    }
};

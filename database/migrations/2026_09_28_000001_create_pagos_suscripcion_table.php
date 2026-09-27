<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pagos del Plan Profesional hechos en USDC a la tesorería del proyecto y verificados
     * en la blockchain. Cada pago confirmado extiende el plan de la empresa.
     */
    public function up(): void
    {
        Schema::create('pagos_suscripcion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('plan', 30)->default('profesional');
            $table->decimal('monto', 10, 2);
            $table->unsignedSmallInteger('dias');
            $table->string('red', 50);
            $table->string('tx_hash', 66)->unique();
            $table->string('wallet_destino', 42);
            $table->string('pagador', 42)->nullable();
            $table->unsignedBigInteger('bloque')->nullable();
            $table->string('estado', 20)->default('verificando');
            $table->string('error', 255)->nullable();
            $table->timestamp('periodo_desde')->nullable();
            $table->timestamp('periodo_hasta')->nullable();
            $table->timestamp('confirmado_en')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
        });

        // Fila única editable por el admin: a qué wallet del proyecto se paga el plan y cuánto cuesta.
        Schema::create('configuraciones_suscripcion', function (Blueprint $table) {
            $table->id();
            $table->string('tesoreria', 42)->nullable();
            $table->decimal('precio_usdc', 10, 2);
            $table->unsignedSmallInteger('dias');
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Para no repetir el aviso de "tu plan vence pronto" en cada ejecución del scheduler.
        Schema::table('empresas', function (Blueprint $table) {
            $table->timestamp('plan_aviso_vencimiento_en')->nullable()->after('plan_expira_en');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('plan_aviso_vencimiento_en');
        });

        Schema::dropIfExists('configuraciones_suscripcion');
        Schema::dropIfExists('pagos_suscripcion');
    }
};

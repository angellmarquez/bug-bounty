<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos de la verificación on-chain del pago de un bounty: a qué wallet se debía pagar
     * (se fija al registrar la transacción), quién pagó, en qué bloque y por qué falló.
     */
    public function up(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->string('bounty_wallet_destino', 42)->nullable()->after('bounty_red');
            $table->string('bounty_pagador', 42)->nullable()->after('bounty_wallet_destino');
            $table->unsignedBigInteger('bounty_bloque')->nullable()->after('bounty_pagador');
            $table->timestamp('bounty_tx_registrada_en')->nullable()->after('bounty_bloque');
            $table->string('bounty_error', 255)->nullable()->after('bounty_tx_registrada_en');
        });
    }

    public function down(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn([
                'bounty_wallet_destino',
                'bounty_pagador',
                'bounty_bloque',
                'bounty_tx_registrada_en',
                'bounty_error',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->boolean('tiene_recompensas')->default(false)->after('es_publico');
            $table->decimal('recompensa_min', 10, 2)->nullable()->after('tiene_recompensas');
            $table->decimal('recompensa_max', 10, 2)->nullable()->after('recompensa_min');
            $table->string('moneda', 10)->default('USDC')->after('recompensa_max');
            $table->json('tabla_recompensas')->nullable()->after('moneda');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->string('plan', 30)->default('comunitario')->after('estado');
            $table->timestamp('plan_expira_en')->nullable()->after('plan');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('wallet_address', 100)->nullable()->after('email');
            $table->string('wallet_red', 50)->default('Polygon')->after('wallet_address');
        });

        Schema::table('reportes', function (Blueprint $table) {
            $table->decimal('bounty_monto', 10, 2)->nullable()->after('puntuacion_cvss');
            $table->string('bounty_moneda', 10)->default('USDC')->after('bounty_monto');
            $table->string('bounty_estado', 30)->default('sin_bounty')->after('bounty_moneda');
            $table->string('bounty_tx_hash', 120)->nullable()->unique()->after('bounty_estado');
            $table->string('bounty_red', 50)->nullable()->after('bounty_tx_hash');
            $table->timestamp('bounty_pagado_en')->nullable()->after('bounty_red');
        });
    }

    public function down(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn([
                'bounty_monto',
                'bounty_moneda',
                'bounty_estado',
                'bounty_tx_hash',
                'bounty_red',
                'bounty_pagado_en',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wallet_address', 'wallet_red']);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['plan', 'plan_expira_en']);
        });

        Schema::table('programas', function (Blueprint $table) {
            $table->dropColumn([
                'tiene_recompensas',
                'recompensa_min',
                'recompensa_max',
                'moneda',
                'tabla_recompensas',
            ]);
        });
    }
};

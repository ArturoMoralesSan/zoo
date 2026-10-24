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
        /*
         * Minutos de espera cuando el usuario agota sus reintentos.
         */
        Schema::table('card_rarity_settings', function (Blueprint $table) {
            $table->unsignedInteger('cooldown_minutes')
                ->default(1440)
                ->after('retries');
        });

        /*
         * Valor propio de la tarjeta (solo si duel_settings_source = card).
         */
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedInteger('cooldown_minutes')
                ->nullable()
                ->after('retries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('cooldown_minutes');
        });

        Schema::table('card_rarity_settings', function (Blueprint $table) {
            $table->dropColumn('cooldown_minutes');
        });
    }
};

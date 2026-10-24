<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
         * Origen de la configuración de duelos:
         * - rarity: usa la configuración de la rareza.
         * - card: usa los valores propios de la tarjeta.
         */
        Schema::table('cards', function (Blueprint $table) {
            $table->string('duel_settings_source')
                ->default('rarity')
                ->after('fuerza');
        });

        DB::table('cards')
            ->where('retry_type', 'card')
            ->update(['duel_settings_source' => 'card']);

        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('retry_type');

            $table->unsignedInteger('questions_per_duel')
                ->nullable()
                ->default(null)
                ->change();

            $table->unsignedInteger('retries')
                ->nullable()
                ->default(null)
                ->change();
        });

        Schema::table('species_categories', function (Blueprint $table) {
            $table->dropColumn('retry_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('species_categories', function (Blueprint $table) {
            $table->unsignedInteger('retry_limit')
                ->default(3)
                ->after('description');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->string('retry_type')
                ->default('card')
                ->after('questions_per_duel');
        });

        DB::table('cards')
            ->where('duel_settings_source', 'rarity')
            ->update(['retry_type' => 'category']);

        DB::table('cards')
            ->whereNull('questions_per_duel')
            ->update(['questions_per_duel' => 3]);

        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('duel_settings_source');

            $table->unsignedInteger('questions_per_duel')
                ->default(3)
                ->nullable(false)
                ->change();

            $table->unsignedInteger('retries')
                ->nullable()
                ->default(3)
                ->change();
        });
    }
};

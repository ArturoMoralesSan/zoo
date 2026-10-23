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
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedTinyInteger('astucia')
                ->default(50)
                ->after('sort_order');

            $table->unsignedTinyInteger('rapidez')
                ->default(50)
                ->after('astucia');

            $table->unsignedTinyInteger('fuerza')
                ->default(50)
                ->after('rapidez');

            $table->unsignedInteger('questions_per_duel')
                ->default(3)
                ->after('fuerza');

            $table->string('retry_type')
                ->default('card')
                ->after('questions_per_duel');

            $table->unsignedInteger('retries')
                ->nullable()
                ->default(3)
                ->after('retry_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'astucia',
                'rapidez',
                'fuerza',
                'questions_per_duel',
                'retry_type',
                'retries',
            ]);
        });
    }
};

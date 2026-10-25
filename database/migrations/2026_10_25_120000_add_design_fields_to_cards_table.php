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
         * Diseño de la plantilla de la tarjeta:
         * - Color de los degradados y color de acento (null = automático).
         * - Fuente, peso y tamaño del nombre de la especie.
         */
        Schema::table('cards', function (Blueprint $table) {
            $table->string('fade_color', 7)
                ->default('#4f6b5a')
                ->after('generated_image');

            $table->string('accent_color', 5)
                ->nullable()
                ->after('fade_color');

            $table->string('title_font_family')
                ->default('cinzel')
                ->after('accent_color');

            $table->unsignedSmallInteger('title_font_weight')
                ->default(800)
                ->after('title_font_family');

            $table->string('title_font_size', 4)
                ->default('6xl')
                ->after('title_font_weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'fade_color',
                'accent_color',
                'title_font_family',
                'title_font_weight',
                'title_font_size',
            ]);
        });
    }
};

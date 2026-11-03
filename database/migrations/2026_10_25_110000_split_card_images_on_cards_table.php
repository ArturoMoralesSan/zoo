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
         * La imagen de la tarjeta se divide en:
         * - Miniatura (antes card_image).
         * - Ilustración de fondo para la plantilla de la tarjeta.
         * - Imagen final generada a partir de la plantilla.
         */
        Schema::table('cards', function (Blueprint $table) {
            $table->renameColumn('card_image', 'thumbnail_image');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->string('artwork_image')
                ->nullable()
                ->after('thumbnail_image');

            $table->string('generated_image')
                ->nullable()
                ->after('artwork_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'artwork_image',
                'generated_image',
            ]);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->renameColumn('thumbnail_image', 'card_image');
        });
    }
};

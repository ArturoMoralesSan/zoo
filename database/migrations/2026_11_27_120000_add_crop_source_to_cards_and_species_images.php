<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copia reducida de la foto completa y recuadro del último recorte, para
     * volver a recortar sin partir de la imagen ya recortada y comprimida.
     * Solo la ilustración de la tarjeta y la imagen principal de la especie.
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('artwork_source')->nullable()->after('artwork_image');
            $table->json('artwork_crop')->nullable()->after('artwork_source');
        });

        Schema::table('species_images', function (Blueprint $table) {
            $table->string('source_path')->nullable()->after('path');
            $table->json('crop')->nullable()->after('source_path');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['artwork_source', 'artwork_crop']);
        });

        Schema::table('species_images', function (Blueprint $table) {
            $table->dropColumn(['source_path', 'crop']);
        });
    }
};

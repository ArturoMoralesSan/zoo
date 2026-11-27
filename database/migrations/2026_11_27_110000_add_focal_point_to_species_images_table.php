<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Punto focal de las imágenes principal y de galería (0 a 1, desde la
     * esquina superior izquierda). La app recorta con cover en marcos de
     * distinta proporción y lo mantiene a la vista; null = centro.
     */
    public function up(): void
    {
        Schema::table('species_images', function (Blueprint $table) {
            $table->decimal('focal_x', 5, 4)->nullable()->after('alt_text');
            $table->decimal('focal_y', 5, 4)->nullable()->after('focal_x');
        });
    }

    public function down(): void
    {
        Schema::table('species_images', function (Blueprint $table) {
            $table->dropColumn(['focal_x', 'focal_y']);
        });
    }
};

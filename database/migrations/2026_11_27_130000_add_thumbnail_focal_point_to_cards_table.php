<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Punto focal de la miniatura (0 a 1, desde la esquina superior
     * izquierda). Se sube 1:1, pero la app la recorta en marcos de 0.7:1 a
     * 2:1 (mazo, diálogos del duelo) y lo mantiene a la vista; null = centro.
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->decimal('thumbnail_focal_x', 5, 4)->nullable()->after('thumbnail_image');
            $table->decimal('thumbnail_focal_y', 5, 4)->nullable()->after('thumbnail_focal_x');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['thumbnail_focal_x', 'thumbnail_focal_y']);
        });
    }
};

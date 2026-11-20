<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Peso de la imagen antes de comprimirla a WebP, para mostrar el ahorro.
 * Los originales no se conservan. Null en imágenes anteriores a la compresión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('species_images', function (Blueprint $table) {
            $table->unsignedBigInteger('original_bytes')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('species_images', function (Blueprint $table) {
            $table->dropColumn('original_bytes');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Peso de cada imagen antes de comprimirla a WebP, para mostrar el ahorro
     * en el panel: { generated, thumbnail, artwork } en bytes. Los originales
     * no se conservan, así que es el único registro de su tamaño.
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->json('image_original_bytes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('image_original_bytes');
        });
    }
};

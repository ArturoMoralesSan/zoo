<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ícono y color del aviso de temporada. Guardan claves del catálogo
 * resources/data/point-season-styles.json; null = predeterminado de la app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_modes', function (Blueprint $table) {
            $table->string('icon', 40)->nullable()->after('public_message');
            $table->string('color', 40)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('point_modes', function (Blueprint $table) {
            $table->dropColumn(['icon', 'color']);
        });
    }
};

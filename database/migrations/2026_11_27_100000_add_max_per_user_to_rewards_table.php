<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canjes permitidos por usuario. Los puntos no se descuentan al canjear,
     * así que sin límite un mismo usuario podía agotar el stock. Las
     * recompensas existentes quedan en 1; null = sin límite.
     */
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->unsignedInteger('max_per_user')
                ->nullable()
                ->default(1)
                ->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->dropColumn('max_per_user');
        });
    }
};

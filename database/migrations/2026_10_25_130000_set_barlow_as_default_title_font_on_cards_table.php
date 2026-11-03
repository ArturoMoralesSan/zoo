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
         * Barlow es la fuente predeterminada del nombre en las tarjetas nuevas.
         * Las tarjetas existentes conservan la fuente que tenían.
         */
        Schema::table('cards', function (Blueprint $table) {
            $table->string('title_font_family')
                ->default('barlow')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('title_font_family')
                ->default('cinzel')
                ->change();
        });
    }
};

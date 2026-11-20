<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Identificador estable de cada tarjeta. Los id cambian entre servidores;
 * el uuid permite reconocer una tarjeta al importar un paquete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('cards')
            ->whereNull('uuid')
            ->orderBy('id')
            ->select('id')
            ->chunkById(200, function ($cards) {
                foreach ($cards as $card) {
                    DB::table('cards')
                        ->where('id', $card->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        Schema::table('cards', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};

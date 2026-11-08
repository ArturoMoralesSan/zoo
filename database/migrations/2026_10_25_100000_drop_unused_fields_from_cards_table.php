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
         * Campos que ya no se usan:
         * - Textos descriptivos de la tarjeta y del modelo.
         * - El modelo 3D es solo un archivo GLB subido (sin URL externa ni formato).
         */
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'edition',
                'model_name',
                'model_url',
                'model_format',
                'model_description',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('edition')
                ->nullable()
                ->after('rarity');

            $table->text('description')
                ->nullable()
                ->after('edition');

            $table->string('model_name')
                ->nullable()
                ->after('card_image');

            $table->text('model_url')
                ->nullable()
                ->after('model_file');

            $table->string('model_format')
                ->nullable()
                ->after('model_url');

            $table->text('model_description')
                ->nullable()
                ->after('model_format');
        });
    }
};

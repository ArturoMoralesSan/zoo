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
         * Tamaño real del modelo 3D (en metros).
         * model_file sigue siendo el archivo que descarga el juego (escalado);
         * model_original_file es el archivo subido, en el disco privado.
         * Todo es opcional: los registros existentes no cambian.
         */
        Schema::table('cards', function (Blueprint $table) {
            $table->string('model_original_file')
                ->nullable()
                ->after('model_file');

            $table->decimal('model_target_height', 12, 4)
                ->nullable()
                ->after('model_original_file');

            $table->decimal('model_original_height', 12, 4)
                ->nullable()
                ->after('model_target_height');

            $table->decimal('model_final_height', 12, 4)
                ->nullable()
                ->after('model_original_height');

            $table->decimal('model_final_width', 12, 4)
                ->nullable()
                ->after('model_final_height');

            $table->decimal('model_final_depth', 12, 4)
                ->nullable()
                ->after('model_final_width');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'model_original_file',
                'model_target_height',
                'model_original_height',
                'model_final_height',
                'model_final_width',
                'model_final_depth',
            ]);
        });
    }
};

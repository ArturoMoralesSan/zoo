<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();

            /*
             * Especie a la que pertenece la tarjeta.
             *
             * Una especie puede tener varias tarjetas:
             * - Común
             * - Rara
             * - Edición especial
             * - Edición limitada
             */
            $table->foreignId('species_id')
                ->constrained('species')
                ->cascadeOnDelete();

            /*
             * Información de la tarjeta
             */
            $table->string('name');

            $table->string('rarity')
                ->default('comun');

            $table->string('edition')
                ->nullable();

            $table->text('description')
                ->nullable();

            /*
             * Imagen propia de la tarjeta
             */
            $table->string('card_image')
                ->nullable();

            /*
             * Modelo 3D
             */
            $table->string('model_name')
                ->nullable();

            $table->string('model_file')
                ->nullable();

            $table->text('model_url')
                ->nullable();

            $table->string('model_format')
                ->nullable();

            $table->text('model_description')
                ->nullable();

            /*
             * Estado
             */
            $table->boolean('is_active')
                ->default(true);

            /*
             * Orden para mostrar las tarjetas
             */
            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();

            $table->index([
                'species_id',
                'is_active',
            ]);

            $table->index([
                'rarity',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
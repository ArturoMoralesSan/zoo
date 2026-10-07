<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();

            /*
             * Card a la que pertenece esta pregunta.
             */
            $table->foreignId('card_id')
                ->constrained('cards')
                ->cascadeOnDelete();

            /*
             * Pregunta que verá el jugador.
             */
            $table->text('question');

            /*
             * Permite activar/desactivar la pregunta
             * sin eliminarla.
             */
            $table->boolean('is_active')
                ->default(true);

            /*
             * Orden de la pregunta dentro del quiz.
             */
            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();

            $table->index([
                'card_id',
                'is_active',
            ]);

            $table->index([
                'card_id',
                'sort_order',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
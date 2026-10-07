<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();

            /*
             * Pregunta a la que pertenece la respuesta.
             */
            $table->foreignId('quiz_question_id')
                ->constrained('quiz_questions')
                ->cascadeOnDelete();

            /*
             * Texto de la respuesta.
             */
            $table->text('answer');

            /*
             * Indica cuál de las tres respuestas
             * es la correcta.
             */
            $table->boolean('is_correct')
                ->default(false);

            /*
             * Orden de aparición.
             */
            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();

            $table->index([
                'quiz_question_id',
                'sort_order',
            ]);

            $table->index([
                'quiz_question_id',
                'is_correct',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};

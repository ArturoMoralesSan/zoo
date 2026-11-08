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
         * Cada duelo iniciado por un usuario.
         *
         * Guarda las preguntas servidas para validar
         * que la respuesta corresponda exactamente a ellas.
         */
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('card_id')
                ->constrained('cards')
                ->cascadeOnDelete();

            $table->json('question_ids');

            /*
             * pending | passed | failed | expired
             */
            $table->string('status')
                ->default('pending');

            $table->unsignedInteger('correct_count')
                ->nullable();

            $table->unsignedInteger('total_questions');

            $table->timestamp('expires_at');

            $table->timestamp('submitted_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'card_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};

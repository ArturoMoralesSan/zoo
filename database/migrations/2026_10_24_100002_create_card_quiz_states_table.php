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
         * Reintentos fallidos y bloqueo por usuario y tarjeta.
         */
        Schema::create('card_quiz_states', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('card_id')
                ->constrained('cards')
                ->cascadeOnDelete();

            $table->unsignedInteger('failed_attempts')
                ->default(0);

            $table->timestamp('locked_until')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'user_id',
                'card_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_quiz_states');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_captures', function (Blueprint $table) {
            $table->id();

            /*
             * Usuario que obtuvo la tarjeta.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
             * Tarjeta obtenida.
             */
            $table->foreignId('card_id')
                ->constrained('cards')
                ->cascadeOnDelete();

            /*
             * Fecha y hora en que se obtuvo la tarjeta.
             */
            $table->timestamp('captured_at')
                ->useCurrent();

            /*
             * Evidencia opcional de la captura.
             * Puede ser una fotografía tomada desde la app.
             */
            $table->string('capture_image')
                ->nullable();

            /*
             * Ubicación opcional donde se realizó la captura.
             */
            $table->decimal('latitude', 10, 7)
                ->nullable();

            $table->decimal('longitude', 10, 7)
                ->nullable();

            $table->timestamps();

            /*
             * Un usuario no puede obtener dos veces
             * la misma tarjeta.
             */
            $table->unique([
                'user_id',
                'card_id',
            ]);

            $table->index([
                'user_id',
                'captured_at',
            ]);

            $table->index([
                'card_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_captures');
    }
};
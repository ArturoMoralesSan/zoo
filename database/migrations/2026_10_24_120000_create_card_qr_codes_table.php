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
         * Códigos QR para iniciar duelos.
         *
         * El QR contiene únicamente el token aleatorio;
         * nunca el id de la tarjeta ni el del QR.
         */
        Schema::create('card_qr_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('card_id')
                ->constrained('cards')
                ->cascadeOnDelete();

            /*
             * Referencia interna, por ejemplo la ubicación del QR.
             */
            $table->string('label')
                ->nullable();

            $table->string('token', 64)
                ->unique();

            $table->boolean('is_active')
                ->default(true);

            /*
             * null = sin vencimiento.
             */
            $table->timestamp('expires_at')
                ->nullable();

            $table->unsignedInteger('scans_count')
                ->default(0);

            $table->timestamp('last_scanned_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'card_id',
                'is_active',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_qr_codes');
    }
};

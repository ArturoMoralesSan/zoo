<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exportaciones e importaciones de paquetes de tarjetas (.zip).
 * Cada fila sigue el trabajo en cola y guarda el archivo en el disco local.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('type', 20);
            $table->string('status', 30);
            $table->json('options')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_transfers');
    }
};

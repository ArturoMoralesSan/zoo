<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('reward_id')
                ->constrained()
                ->restrictOnDelete();

            // Puntos utilizados en el momento del canje.
            $table->unsignedInteger('points');

            // Folio único del canje.
            $table->string('folio', 30)
                ->unique();

            // completed, cancelled, pending, etc.
            $table->string('status', 30)
                ->default('completed');

            $table->timestamp('redeemed_at')
                ->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('reward_id');
            $table->index('status');
            $table->index('redeemed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
    }
};

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
         * Créditos de los recursos de las tarjetas: varios por recurso, cada
         * uno con un papel (origen, basado en, incluye). Reemplaza a
         * asset_credits (un solo crédito por recurso), que no se modifica.
         */
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->morphs('creditable');
            $table->string('asset', 32);
            $table->string('role', 32);
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('license_id')
                ->constrained('licenses')
                ->restrictOnDelete();

            $table->string('title')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_url', 2048)->nullable();
            $table->string('source_name', 32)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->date('acquired_at')->nullable();
            $table->boolean('modified')->default(false);
            $table->string('modification_note')->nullable();
            $table->string('attribution_override', 1000)->nullable();

            // Otro recurso de la misma tarjeta del que sale este (render del modelo…).
            $table->string('based_on_asset', 32)->nullable();

            // Imágenes generadas con IA (solo para el panel; nunca se publican).
            $table->text('ai_prompt')->nullable();
            $table->timestamp('ai_checked_at')->nullable();

            // Solo para el panel; nunca se publica.
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['creditable_type', 'creditable_id', 'asset', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};

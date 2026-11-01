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
         * Crédito de un recurso (ilustración, miniatura o modelo 3D).
         * Polimórfico para poder añadir otros recursos (p. ej. imágenes de
         * especies) sin otra migración. Las tarjetas existentes no reciben
         * créditos: aparecen como "Falta licencia".
         */
        Schema::create('asset_credits', function (Blueprint $table) {
            $table->id();
            $table->morphs('creditable');
            $table->string('asset', 32);

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

            $table->foreignId('derived_from_id')
                ->nullable()
                ->constrained('asset_credits')
                ->nullOnDelete();

            $table->boolean('show_in_credits')->default(true);

            // Solo para el panel; nunca se publica.
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['creditable_type', 'creditable_id', 'asset']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_credits');
    }
};

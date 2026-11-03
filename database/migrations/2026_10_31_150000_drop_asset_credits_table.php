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
         * asset_credits (un crédito por recurso) se reemplazó por credits;
         * sus datos se copiaron en la migración anterior.
         */
        Schema::dropIfExists('asset_credits');
    }

    /**
     * Reverse the migrations.
     *
     * Recrea la tabla vacía (los créditos siguen en credits).
     */
    public function down(): void
    {
        Schema::create('asset_credits', function (Blueprint $table) {
            $table->id();
            $table->morphs('creditable');
            $table->string('asset', 32);
            $table->foreignId('license_id')->constrained('licenses')->restrictOnDelete();
            $table->string('title')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_url', 2048)->nullable();
            $table->string('source_name', 32)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->date('acquired_at')->nullable();
            $table->boolean('modified')->default(false);
            $table->string('modification_note')->nullable();
            $table->string('attribution_override', 1000)->nullable();
            $table->foreignId('derived_from_id')->nullable()->constrained('asset_credits')->nullOnDelete();
            $table->boolean('show_in_credits')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['creditable_type', 'creditable_id', 'asset']);
        });
    }
};

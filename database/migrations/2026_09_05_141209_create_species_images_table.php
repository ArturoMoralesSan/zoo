<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('species_id')
                ->constrained('species')
                ->cascadeOnDelete();

            $table->string('type')->default('gallery');
            // main, thumbnail, card, gallery

            $table->string('path');

            $table->string('alt_text')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['species_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('species_images');
    }
};
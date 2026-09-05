<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species_models', function (Blueprint $table) {
            $table->id();

            $table->foreignId('species_id')
                ->constrained('species')
                ->cascadeOnDelete();

            $table->string('name');

            $table->string('path')->nullable();

            $table->string('url')->nullable();

            $table->string('format')->nullable();
            // glb, gltf, usdz, etc.

            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('species_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('species_models');
    }
};
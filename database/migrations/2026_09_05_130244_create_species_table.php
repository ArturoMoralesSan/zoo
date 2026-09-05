<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species', function (Blueprint $table) {
            $table->id();

            $table->foreignId('species_category_id')
                ->constrained('species_categories')
                ->restrictOnDelete();

            $table->string('common_name');
            $table->string('scientific_name');
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            $table->string('habitat')->nullable();
            $table->string('origin')->nullable();
            $table->string('diet')->nullable();

            $table->string('conservation_status')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('species');
    }
};
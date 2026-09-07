<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('species_id')
                ->constrained('species')
                ->cascadeOnDelete();

            $table->foreignId('zone_id')
                ->nullable()
                ->after('species_id')
                ->constrained('zoo_zones')
                ->nullOnDelete();

            $table->string('name');

            $table->decimal('latitude', 10, 7);

            $table->decimal('longitude', 10, 7);

            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('species_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('species_locations');
    }
};
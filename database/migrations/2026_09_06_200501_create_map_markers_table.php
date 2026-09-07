<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_markers', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();

            $table->string('type')->nullable();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->string('icon')->nullable();
            $table->string('color')->nullable();

            $table->foreignId('zone_id')
                ->nullable()
                ->constrained('zoo_zones')
                ->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('type');
            $table->index('zone_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_markers');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_paths', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->text('description')->nullable();

            $table->json('coordinates');

            $table->unsignedInteger('distance')->nullable();

            $table->unsignedInteger('estimated_time')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('order')->default(0);

            $table->timestamps();

            $table->index('is_active');
            $table->index('order');

            $table->foreignId('zone_id')
                ->nullable()
                ->after('id')
                ->constrained('zoo_zones')
                ->nullOnDelete();

            $table->index('zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_paths');
    }
};
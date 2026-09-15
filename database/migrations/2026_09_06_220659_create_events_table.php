<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            $table->string('type')->nullable();

            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();

            $table->foreignId('zoo_zone_id')
                ->nullable()
                ->constrained('zoo_zones')
                ->nullOnDelete();

            $table->string('image')->nullable();

            $table->unsignedInteger('capacity')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('type');
            $table->index('start_at');
            $table->index('is_active');
            $table->index('zoo_zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

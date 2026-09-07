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
        Schema::create('zoo_zones', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->text('description')
                ->nullable();

            $table->string('type')
                ->nullable();

            // GeoJSON del polígono de la zona
            $table->json('geometry')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            // Índices
            $table->index('is_active');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zoo_zones');
    }
};
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
         * Catálogo de licencias. Solo lo administra LicenseSeeder.
         */
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short_name');
            $table->string('url')->nullable();
            $table->boolean('requires_attribution')->default(false);
            $table->boolean('allows_commercial')->default(true);
            $table->boolean('allows_modification')->default(true);
            $table->boolean('share_alike')->default(false);
            $table->string('attribution_template')->nullable();
            $table->boolean('is_selectable')->default(true);

            // Contenido propio o con licencia privada: nunca se publica.
            $table->boolean('is_proprietary')->default(false);

            $table->string('summary', 500);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species_tag', function (Blueprint $table) {
            $table->foreignId('species_id')
                ->constrained('species')
                ->cascadeOnDelete();

            $table->foreignId('species_tag_id')
                ->constrained('species_tags')
                ->cascadeOnDelete();

            $table->primary([
                'species_id',
                'species_tag_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('species_tag');
    }
};
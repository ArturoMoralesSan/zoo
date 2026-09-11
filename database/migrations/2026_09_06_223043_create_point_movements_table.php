<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->integer('points');

            $table->string('type', 100);

            $table->text('description')->nullable();

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_movements');
    }
};
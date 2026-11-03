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
        Schema::table('species_categories', function (Blueprint $table) {
            $table->unsignedInteger('retry_limit')
                ->default(3)
                ->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('species_categories', function (Blueprint $table) {
            $table->dropColumn('retry_limit');
        });
    }
};

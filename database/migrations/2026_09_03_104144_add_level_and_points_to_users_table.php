<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('level_id')
                ->nullable()
                ->after('remember_token')
                ->constrained('levels')
                ->nullOnDelete();

            $table->unsignedInteger('points')
                ->default(0)
                ->after('level_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['level_id']);
            $table->dropColumn(['level_id', 'points']);
        });
    }
};

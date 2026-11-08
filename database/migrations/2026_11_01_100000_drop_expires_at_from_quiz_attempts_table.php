<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Los duelos ya no tienen tiempo límite: un duelo queda pendiente
     * hasta que se responde. Estados: pending | passed | failed
     * (los duelos "expired" anteriores se conservan como historial).
     */
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->timestamp('expires_at')
                ->nullable()
                ->after('total_questions');
        });
    }
};

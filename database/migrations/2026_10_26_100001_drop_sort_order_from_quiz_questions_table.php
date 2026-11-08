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
         * Las preguntas de cada duelo se eligen al azar:
         * su orden no se usa.
         */
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropIndex(['card_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')
                ->default(0)
                ->after('is_active');

            $table->index(['card_id', 'sort_order']);
        });
    }
};

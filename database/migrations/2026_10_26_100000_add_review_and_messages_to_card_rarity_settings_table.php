<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
         * Por rareza:
         * - Si al agotar los intentos se muestran las respuestas correctas.
         * - Mensajes del resultado del duelo (vacío = mensaje predeterminado).
         */
        Schema::table('card_rarity_settings', function (Blueprint $table) {
            $table->boolean('reveal_answers_on_lockout')
                ->default(false)
                ->after('cooldown_minutes');

            $table->string('win_message')
                ->nullable()
                ->after('reveal_answers_on_lockout');

            $table->string('retry_message')
                ->nullable()
                ->after('win_message');

            $table->string('locked_message')
                ->nullable()
                ->after('retry_message');
        });

        /*
         * Las rarezas más difíciles no revelan las respuestas.
         */
        DB::table('card_rarity_settings')
            ->whereIn('rarity', ['comun', 'rara'])
            ->update(['reveal_answers_on_lockout' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_rarity_settings', function (Blueprint $table) {
            $table->dropColumn([
                'reveal_answers_on_lockout',
                'win_message',
                'retry_message',
                'locked_message',
            ]);
        });
    }
};

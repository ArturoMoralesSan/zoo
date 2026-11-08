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
         * Los mensajes del duelo ya no son por rareza: hay un juego de mensajes
         * predeterminados y, opcionalmente, mensajes propios por tarjeta.
         */
        Schema::table('card_rarity_settings', function (Blueprint $table) {
            $table->dropColumn([
                'win_message',
                'retry_message',
                'locked_message',
            ]);
        });

        DB::table('cards')
            ->where('messages_source', 'rarity')
            ->update(['messages_source' => 'default']);

        Schema::table('cards', function (Blueprint $table) {
            $table->string('messages_source')
                ->default('default')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('messages_source')
                ->default('rarity')
                ->change();
        });

        DB::table('cards')
            ->where('messages_source', 'default')
            ->update(['messages_source' => 'rarity']);

        Schema::table('card_rarity_settings', function (Blueprint $table) {
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
    }
};

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
         * Mensajes del resultado del duelo propios de la tarjeta.
         * Con "rarity" se usan los de su rareza; con "card", los de la
         * tarjeta (un mensaje vacío usa el de la rareza).
         */
        Schema::table('cards', function (Blueprint $table) {
            $table->string('messages_source')
                ->default('rarity')
                ->after('cooldown_minutes');

            $table->string('win_message')
                ->nullable()
                ->after('messages_source');

            $table->string('retry_message')
                ->nullable()
                ->after('win_message');

            $table->string('locked_message')
                ->nullable()
                ->after('retry_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'messages_source',
                'win_message',
                'retry_message',
                'locked_message',
            ]);
        });
    }
};

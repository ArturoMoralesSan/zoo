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
         * Mensajes predeterminados del resultado del duelo (una sola fila),
         * editables desde el panel. Un mensaje vacío usa el texto integrado.
         * Cada tarjeta puede reemplazarlos con mensajes propios.
         */
        Schema::create('duel_message_settings', function (Blueprint $table) {
            $table->id();

            $table->string('win_message')
                ->nullable();

            $table->string('retry_message')
                ->nullable();

            $table->string('locked_message')
                ->nullable();

            $table->timestamps();
        });

        DB::table('duel_message_settings')->insert([
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duel_message_settings');
    }
};

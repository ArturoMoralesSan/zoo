<?php

use App\Enums\CardRarity;
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
        Schema::create('card_rarity_settings', function (Blueprint $table) {
            $table->id();

            $table->string('rarity')
                ->unique();

            $table->unsignedInteger('questions_per_duel')
                ->default(3);

            $table->unsignedInteger('retries')
                ->default(3);

            $table->timestamps();
        });

        /*
         * Cada rareza siempre tiene su configuración por defecto.
         */
        $now = now();

        DB::table('card_rarity_settings')->insert(
            array_map(
                fn (CardRarity $rarity) => [
                    'rarity' => $rarity->value,
                    'questions_per_duel' => 3,
                    'retries' => 3,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                CardRarity::cases()
            )
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_rarity_settings');
    }
};

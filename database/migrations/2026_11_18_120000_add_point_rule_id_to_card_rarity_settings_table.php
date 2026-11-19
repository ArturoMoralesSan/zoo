<?php

use App\Enums\CardRarity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regla de puntos que otorga capturar una tarjeta de cada rareza.
 * Null = la rareza no otorga puntos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_rarity_settings', function (Blueprint $table) {
            $table->foreignId('point_rule_id')
                ->nullable()
                ->after('reveal_answers_on_lockout')
                ->constrained('point_rules')
                ->nullOnDelete();
        });

        // Cada rareza empieza con su regla card_capture_<rareza>, la que ya usaba.
        foreach (CardRarity::cases() as $rarity) {
            $ruleId = DB::table('point_rules')
                ->where('type', $rarity->pointRuleType())
                ->value('id');

            if ($ruleId === null) {
                continue;
            }

            DB::table('card_rarity_settings')
                ->where('rarity', $rarity->value)
                ->update(['point_rule_id' => $ruleId]);
        }
    }

    public function down(): void
    {
        Schema::table('card_rarity_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('point_rule_id');
        });
    }
};

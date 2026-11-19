<?php

use App\Enums\CardRarity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cambia las reglas de puntos de captura por especie (que nunca se usaron)
 * por una regla por rareza de tarjeta, que es lo que otorga el duelo.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{name: string, points: int, description: string}>
     */
    private array $oldRules = [
        'species_capture' => [
            'name' => 'Captura de especie',
            'points' => 25,
            'description' => 'Puntos obtenidos al capturar una especie dentro del zoológico.',
        ],
        'rare_species_capture' => [
            'name' => 'Captura de especie rara',
            'points' => 50,
            'description' => 'Puntos adicionales obtenidos al capturar una especie de rareza especial.',
        ],
        'special_species_capture' => [
            'name' => 'Captura de especie edición especial',
            'points' => 100,
            'description' => 'Puntos obtenidos al capturar una especie de edición especial.',
        ],
    ];

    public function up(): void
    {
        DB::table('point_rules')
            ->whereIn('type', array_keys($this->oldRules))
            ->delete();

        $now = now();

        foreach (CardRarity::cases() as $rarity) {
            // No pisa una regla que ya exista (por ejemplo, editada en el panel).
            DB::table('point_rules')->insertOrIgnore([
                'type' => $rarity->pointRuleType(),
                'name' => 'Captura de tarjeta '.mb_strtolower($rarity->label()),
                'points' => $rarity->defaultCapturePoints(),
                'description' => 'Puntos obtenidos al ganar el duelo de una tarjeta '.mb_strtolower($rarity->label()).'.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('point_rules')
            ->whereIn('type', array_map(
                fn (CardRarity $rarity) => $rarity->pointRuleType(),
                CardRarity::cases()
            ))
            ->delete();

        $now = now();

        foreach ($this->oldRules as $type => $rule) {
            DB::table('point_rules')->insertOrIgnore($rule + [
                'type' => $type,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};

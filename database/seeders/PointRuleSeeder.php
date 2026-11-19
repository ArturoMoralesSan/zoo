<?php

namespace Database\Seeders;

use App\Enums\CardRarity;
use App\Models\PointRule;
use Illuminate\Database\Seeder;

class PointRuleSeeder extends Seeder
{
    public function run(): void
    {
        PointRule::updateOrCreate(
            ['type' => 'ticket_purchase'],
            [
                'name' => 'Compra de boleto',
                'points' => 10,
                'description' => 'Puntos obtenidos por cada boleto de entrada comprado.',
                'is_active' => true,
            ]
        );

        // Una regla por rareza; firstOrCreate para no pisar los puntos
        // que se hayan ajustado en el panel.
        foreach (CardRarity::cases() as $rarity) {
            PointRule::firstOrCreate(
                ['type' => $rarity->pointRuleType()],
                [
                    'name' => 'Captura de tarjeta '.mb_strtolower($rarity->label()),
                    'points' => $rarity->defaultCapturePoints(),
                    'description' => 'Puntos obtenidos al ganar el duelo de una tarjeta '.mb_strtolower($rarity->label()).'.',
                    'is_active' => true,
                ]
            );
        }

        PointRule::updateOrCreate(
            ['type' => 'donation'],
            [
                'name' => 'Donación',
                'points' => 5,
                'description' => 'Puntos obtenidos por realizar una donación al zoológico.',
                'is_active' => true,
            ]
        );

        PointRule::updateOrCreate(
            ['type' => 'event_attendance'],
            [
                'name' => 'Asistencia a evento',
                'points' => 20,
                'description' => 'Puntos obtenidos por asistir a un evento del zoológico.',
                'is_active' => true,
            ]
        );
    }
}

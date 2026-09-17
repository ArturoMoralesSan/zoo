<?php

namespace Database\Seeders;

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

        PointRule::updateOrCreate(
            ['type' => 'species_capture'],
            [
                'name' => 'Captura de especie',
                'points' => 25,
                'description' => 'Puntos obtenidos al capturar una especie dentro del zoológico.',
                'is_active' => true,
            ]
        );

        PointRule::updateOrCreate(
            ['type' => 'rare_species_capture'],
            [
                'name' => 'Captura de especie rara',
                'points' => 50,
                'description' => 'Puntos adicionales obtenidos al capturar una especie de rareza especial.',
                'is_active' => true,
            ]
        );

        PointRule::updateOrCreate(
            ['type' => 'special_species_capture'],
            [
                'name' => 'Captura de especie edición especial',
                'points' => 100,
                'description' => 'Puntos obtenidos al capturar una especie de edición especial.',
                'is_active' => true,
            ]
        );

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

<?php

namespace Database\Seeders;

use App\Models\Reward;
use Illuminate\Database\Seeder;

class RewardSeeder extends Seeder
{
    public function run(): void
    {
        Reward::updateOrCreate(
            ['name' => 'Sticker del Zoológico'],
            [
                'description' => 'Sticker coleccionable oficial del Zoológico Sahuatoba.',
                'points' => 100,
                'stock' => 100,
                'image' => null,
                'is_active' => true,
            ]
        );

        Reward::updateOrCreate(
            ['name' => 'Llavero del Zoológico'],
            [
                'description' => 'Llavero oficial del Zoológico Sahuatoba.',
                'points' => 250,
                'stock' => 50,
                'image' => null,
                'is_active' => true,
            ]
        );

        Reward::updateOrCreate(
            ['name' => 'Foto conmemorativa'],
            [
                'description' => 'Foto conmemorativa de la visita al Zoológico Sahuatoba.',
                'points' => 400,
                'stock' => 30,
                'image' => null,
                'is_active' => true,
            ]
        );

        Reward::updateOrCreate(
            ['name' => 'Playera del Zoológico'],
            [
                'description' => 'Playera oficial del Zoológico Sahuatoba.',
                'points' => 1000,
                'stock' => 20,
                'image' => null,
                'is_active' => true,
            ]
        );

        Reward::updateOrCreate(
            ['name' => 'Boleto de entrada gratis'],
            [
                'description' => 'Boleto de entrada gratuito para una visita al zoológico.',
                'points' => 1500,
                'stock' => 10,
                'image' => null,
                'is_active' => true,
            ]
        );
    }
}

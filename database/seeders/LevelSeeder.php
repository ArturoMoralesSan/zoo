<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        Level::insert([
            [
                'name' => 'Bronce',
                'min_points' => 0,
                'max_points' => 499,
                'description' => 'Nivel inicial del visitante.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Plata',
                'min_points' => 500,
                'max_points' => 1499,
                'description' => 'Visitante que comienza a explorar y coleccionar.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Oro',
                'min_points' => 1500,
                'max_points' => 2999,
                'description' => 'Visitante avanzado y coleccionista.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Platino',
                'min_points' => 3000,
                'max_points' => null,
                'description' => 'Visitante experto y gran coleccionista.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
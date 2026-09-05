<?php

namespace Database\Seeders;

use App\Models\SpeciesCategory;
use Illuminate\Database\Seeder;

class SpeciesCategorySeeder extends Seeder
{
    public function run(): void
    {
        SpeciesCategory::insert([
            [
                'name' => 'Mamíferos',
                'description' => 'Animales mamíferos del zoológico.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Aves',
                'description' => 'Aves del zoológico.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Reptiles',
                'description' => 'Reptiles del zoológico.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Anfibios',
                'description' => 'Anfibios del zoológico.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Peces',
                'description' => 'Peces del zoológico.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Invertebrados',
                'description' => 'Invertebrados del zoológico.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
<?php

namespace Database\Seeders;

use App\Models\SpeciesTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpeciesTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            'Felino',
            'Mamífero',
            'Ave',
            'Reptil',
            'Anfibio',
            'Pez',
            'Invertebrado',
            'Carnívoro',
            'Herbívoro',
            'Omnívoro',
            'Selva',
            'Bosque',
            'Desierto',
            'Humedal',
            'Montaña',
            'México',
            'Nocturno',
            'Diurno',
            'En peligro',
            'Vulnerable',
        ];

        foreach ($tags as $name) {
            SpeciesTag::updateOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name),
                    'is_active' => true,
                ]
            );
        }
    }
}
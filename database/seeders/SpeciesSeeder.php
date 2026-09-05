<?php

namespace Database\Seeders;

use App\Models\Species;
use App\Models\SpeciesCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpeciesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = SpeciesCategory::pluck('id', 'name');

        $species = [
            [
                'category' => 'Mamíferos',
                'common_name' => 'León',
                'scientific_name' => 'Panthera leo',
                'description' => 'Gran felino conocido por su comportamiento social y su característica melena en los machos.',
                'habitat' => 'Sabana',
                'origin' => 'África',
                'diet' => 'Carnívoro',
                'conservation_status' => 'Vulnerable',
            ],
            [
                'category' => 'Mamíferos',
                'common_name' => 'Jaguar',
                'scientific_name' => 'Panthera onca',
                'description' => 'Felino americano de gran tamaño, reconocido por su fuerza y patrón de manchas.',
                'habitat' => 'Selva y bosque',
                'origin' => 'América',
                'diet' => 'Carnívoro',
                'conservation_status' => 'Casi amenazado',
            ],
            [
                'category' => 'Mamíferos',
                'common_name' => 'Oso negro',
                'scientific_name' => 'Ursus americanus',
                'description' => 'Mamífero de amplia distribución en América del Norte.',
                'habitat' => 'Bosques',
                'origin' => 'América del Norte',
                'diet' => 'Omnívoro',
                'conservation_status' => 'Preocupación menor',
            ],
            [
                'category' => 'Aves',
                'common_name' => 'Águila real',
                'scientific_name' => 'Aquila chrysaetos',
                'description' => 'Ave rapaz de gran tamaño y una de las especies más representativas de México.',
                'habitat' => 'Montañas y zonas abiertas',
                'origin' => 'América del Norte',
                'diet' => 'Carnívoro',
                'conservation_status' => 'Preocupación menor',
            ],
            [
                'category' => 'Reptiles',
                'common_name' => 'Iguana verde',
                'scientific_name' => 'Iguana iguana',
                'description' => 'Reptil arborícola de hábitos principalmente herbívoros.',
                'habitat' => 'Selva tropical',
                'origin' => 'América',
                'diet' => 'Herbívoro',
                'conservation_status' => 'Preocupación menor',
            ],
        ];

        foreach ($species as $item) {
            $categoryId = $categories[$item['category']] ?? null;

            if (!$categoryId) {
                continue;
            }

            Species::updateOrCreate(
                [
                    'scientific_name' => $item['scientific_name'],
                ],
                [
                    'species_category_id' => $categoryId,
                    'common_name' => $item['common_name'],
                    'scientific_name' => $item['scientific_name'],
                    'slug' => Str::slug($item['common_name']),
                    'description' => $item['description'],
                    'habitat' => $item['habitat'],
                    'origin' => $item['origin'],
                    'diet' => $item['diet'],
                    'conservation_status' => $item['conservation_status'],
                    'is_active' => true,
                ]
            );
        }
    }
}
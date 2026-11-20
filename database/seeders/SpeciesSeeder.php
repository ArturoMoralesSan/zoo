<?php

namespace Database\Seeders;

use App\Enums\ConservationStatus;
use App\Enums\Diet;
use App\Enums\Habitat;
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
                'habitats' => [Habitat::Savanna, Habitat::Grassland],
                'origin' => 'África',
                'diet' => Diet::Carnivore,
                'conservation_status' => ConservationStatus::Vulnerable,
            ],
            [
                'category' => 'Mamíferos',
                'common_name' => 'Jaguar',
                'scientific_name' => 'Panthera onca',
                'description' => 'Felino americano de gran tamaño, reconocido por su fuerza y patrón de manchas.',
                'habitats' => [Habitat::TropicalRainforest, Habitat::DryForest, Habitat::Wetland],
                'origin' => 'América',
                'diet' => Diet::Carnivore,
                'conservation_status' => ConservationStatus::NearThreatened,
            ],
            [
                'category' => 'Mamíferos',
                'common_name' => 'Oso negro',
                'scientific_name' => 'Ursus americanus',
                'description' => 'Mamífero de amplia distribución en América del Norte.',
                'habitats' => [Habitat::TemperateForest, Habitat::ConiferousForest, Habitat::Mountain],
                'origin' => 'América del Norte',
                'diet' => Diet::Omnivore,
                'conservation_status' => ConservationStatus::LeastConcern,
            ],
            [
                'category' => 'Aves',
                'common_name' => 'Águila real',
                'scientific_name' => 'Aquila chrysaetos',
                'description' => 'Ave rapaz de gran tamaño y una de las especies más representativas de México.',
                'habitats' => [Habitat::Mountain, Habitat::Grassland, Habitat::Desert],
                'origin' => 'América del Norte',
                'diet' => Diet::Carnivore,
                'conservation_status' => ConservationStatus::LeastConcern,
            ],
            [
                'category' => 'Reptiles',
                'common_name' => 'Iguana verde',
                'scientific_name' => 'Iguana iguana',
                'description' => 'Reptil arborícola de hábitos principalmente herbívoros.',
                'habitats' => [Habitat::TropicalRainforest, Habitat::DryForest, Habitat::Mangrove],
                'origin' => 'América',
                'diet' => Diet::Herbivore,
                'conservation_status' => ConservationStatus::LeastConcern,
            ],
        ];

        foreach ($species as $item) {
            $categoryId = $categories[$item['category']] ?? null;

            if (! $categoryId) {
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
                    'habitats' => $item['habitats'],
                    'origin' => $item['origin'],
                    'diet' => $item['diet'],
                    'conservation_status' => $item['conservation_status'],
                    'is_active' => true,
                ]
            );
        }
    }
}

<?php

namespace Database\Factories;

use App\Enums\ConservationStatus;
use App\Enums\Diet;
use App\Enums\Habitat;
use App\Models\Species;
use App\Models\SpeciesCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SpeciesFactory extends Factory
{
    protected $model = Species::class;

    public function definition(): array
    {
        $commonName = fake()->unique()->words(2, true);

        return [
            'species_category_id' => SpeciesCategory::query()->inRandomOrder()->value('id')
                ?? SpeciesCategory::factory(),

            'common_name' => ucfirst($commonName),

            'scientific_name' => ucfirst(
                fake()->word().' '.fake()->word()
            ),

            'slug' => Str::slug($commonName).'-'.fake()->unique()->numberBetween(1, 99999),

            'description' => fake()->optional()->paragraph(),

            'habitats' => fake()->randomElements(Habitat::cases(), fake()->numberBetween(1, 3)),

            'origin' => fake()->randomElement([
                'México',
                'América del Norte',
                'América del Sur',
                'Centroamérica',
                'África',
                'Asia',
            ]),

            'diet' => fake()->randomElement(Diet::cases()),

            'conservation_status' => fake()->randomElement(ConservationStatus::cases()),

            'is_active' => true,
        ];
    }
}

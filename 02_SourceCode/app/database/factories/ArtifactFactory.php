<?php

namespace Database\Factories;

use App\Models\Artifact;
use Database\Factories\Concerns\WritesInterpretations;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Artifact> */
class ArtifactFactory extends Factory
{
    use WritesInterpretations;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 999999);

        return [
            'museum_id' => fn () => MuseumFactory::main()->id,
            'slug' => 'hien-vat-'.$n,
            'catalog_number' => 'RGM.'.(1000000 + $n),
            'scientific_name' => ucfirst(fake()->word()).' '.fake()->word(),
            'taxon_group' => fake()->randomElement(['dinosauria', 'mammalia', 'aves', 'mineral']),
            'origin_country' => fake()->randomElement(['US', 'NL', 'ID', 'DE']),
            'material' => fake()->randomElement(['Hóa thạch xương', 'Khoáng vật', 'Mẫu nhồi']),
            'length_cm' => fake()->numberBetween(5, 1200),
            'year_collected' => fake()->numberBetween(1850, 2020),
            'collector' => 'Naturalis',
            'is_highlight' => false,
            'status' => 'published',
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Artifact $a) => self::interpretations($a, 'Hiện vật '.$a->catalog_number, 'Specimen '.$a->catalog_number));
    }
}

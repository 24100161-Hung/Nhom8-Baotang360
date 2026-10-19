<?php

namespace Database\Factories;

use App\Models\Museum;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Museum> */
class MuseumFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => 'naturalis-'.fake()->unique()->numberBetween(1, 99999),
            'name' => 'Naturalis Biodiversity Center',
            'city' => 'Leiden',
            'country' => 'NL',
            'website' => 'https://www.naturalis.nl',
            'license' => 'cc_by_sa_3',
            'attribution_text' => 'Naturalis Biodiversity Center, CC BY-SA 3.0',
            'lat' => 52.1647,
            'lng' => 4.4740,
            'status' => 'published',
        ];
    }

    /** Bảo tàng đầu tiên (dùng lại nếu đã có). */
    public static function main(): Museum
    {
        return Museum::query()->orderBy('id')->first() ?? Museum::factory()->create();
    }
}

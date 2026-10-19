<?php

namespace Database\Factories;

use App\Models\Scene;
use Database\Factories\Concerns\WritesInterpretations;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Scene> */
class SceneFactory extends Factory
{
    use WritesInterpretations;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 999999);

        return [
            'museum_id' => fn () => MuseumFactory::main()->id,
            'slug' => 'phong-'.$n,
            'code' => 'room_'.$n,
            'floor' => fake()->numberBetween(0, 3),
            'sort_order' => $n % 100,
            'panorama_path' => 'panoramas/room_'.$n.'.jpg',
            'tiles_state' => 'none',
            'hfov_default' => 100,
            'minimap_x' => fake()->randomFloat(4, 0.05, 0.95),
            'minimap_y' => fake()->randomFloat(4, 0.05, 0.95),
            'status' => 'published',
            'license' => 'cc_by_sa_4',
            'source_name' => 'Wikimedia Commons',
            'is_simulated' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Scene $scene) => self::interpretations($scene, 'Phòng '.$scene->code, 'Room '.$scene->code));
    }
}

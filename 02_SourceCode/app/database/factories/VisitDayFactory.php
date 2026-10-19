<?php

namespace Database\Factories;

use App\Models\VisitDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VisitDay> */
class VisitDayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_date' => today()->addDays(fake()->unique()->numberBetween(3, 3000))->toDateString(),
            'capacity' => 300,
            'sold' => 0,
            'status' => 'open',
        ];
    }

    public function on(string $date): static
    {
        return $this->state(['visit_date' => $date]);
    }
}

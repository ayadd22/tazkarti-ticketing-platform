<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'city_id'  => City::factory(),
            'name'     => fake()->company() . ' ' . fake()->randomElement(['Arena', 'Hall', 'Center', 'Stadium', 'Theater', 'Auditorium']),
            'address'  => fake()->streetAddress(),
            'capacity' => fake()->optional(0.85)->numberBetween(200, 50000),
        ];
    }
}

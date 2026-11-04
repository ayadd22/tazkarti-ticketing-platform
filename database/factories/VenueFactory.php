<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;


class VenueFactory extends Factory
{
    
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

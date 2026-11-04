<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;


class TicketTypeFactory extends Factory
{
    
    private static array $tierNames = ['General Admission', 'Standard', 'VIP', 'Premium', 'Early Bird', 'Student'];

   
    public function definition(): array
    {
        $tierName      = fake()->randomElement(self::$tierNames);
        $totalQuantity = fake()->numberBetween(50, 2000);

        return [
            'event_id'          => Event::factory(),
            'name'              => $tierName,
            'description'       => fake()->optional(0.7)->sentence(),
            'price'             => fake()->randomFloat(2, 5.00, 500.00),
            'total_quantity'    => $totalQuantity,
            'reserved_quantity' => 0,
            'sold_quantity'     => 0,
            'max_per_order'     => fake()->numberBetween(1, 10),
        ];
    }
}

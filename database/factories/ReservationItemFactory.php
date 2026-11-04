<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;


class ReservationItemFactory extends Factory
{
   
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'ticket_type_id' => TicketType::factory(),
            'quantity'       => fake()->numberBetween(1, 4),
            'unit_price'     => fake()->randomFloat(2, 5.00, 500.00),
        ];
    }

    
    public function forReservation(Reservation $reservation): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_id' => $reservation->id,
        ]);
    }

    public function forTicketType(TicketType $ticketType): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_type_id' => $ticketType->id,
            'unit_price'     => $ticketType->price,
        ]);
    }
}

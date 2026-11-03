<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationItem>
 *
 * NOTE: The unique constraint on (reservation_id, ticket_type_id) means you must
 * never create two ReservationItems with the same pair via the factory without
 * providing explicit parent IDs. Seeders handle this by constructing the pair
 * explicitly.
 */
class ReservationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'ticket_type_id' => TicketType::factory(),
            'quantity'       => fake()->numberBetween(1, 4),
            'unit_price'     => fake()->randomFloat(2, 5.00, 500.00),
        ];
    }

    /**
     * Attach to a specific reservation.
     */
    public function forReservation(Reservation $reservation): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_id' => $reservation->id,
        ]);
    }

    /**
     * Attach to a specific ticket type (and snapshot its price).
     */
    public function forTicketType(TicketType $ticketType): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_type_id' => $ticketType->id,
            'unit_price'     => $ticketType->price,
        ]);
    }
}

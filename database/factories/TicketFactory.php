<?php

namespace Database\Factories;

use App\Enums\TicketStatusEnum;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
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
            'code'           => strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
            'status'         => TicketStatusEnum::Valid->value,
            'file_path'      => null,
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
     * Attach to a specific ticket type.
     */
    public function forTicketType(TicketType $ticketType): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_type_id' => $ticketType->id,
        ]);
    }

    /**
     * Mark ticket as used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatusEnum::Used->value,
        ]);
    }

    /**
     * Mark ticket as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatusEnum::Cancelled->value,
        ]);
    }
}

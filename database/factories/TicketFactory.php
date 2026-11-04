<?php

namespace Database\Factories;

use App\Enums\TicketStatusEnum;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;


class TicketFactory extends Factory
{
    
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
        ]);
    }

   
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatusEnum::Used->value,
        ]);
    }

    
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatusEnum::Cancelled->value,
        ]);
    }

   
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatusEnum::Pending->value,
        ]);
    }
}

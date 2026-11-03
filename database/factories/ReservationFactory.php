<?php

namespace Database\Factories;

use App\Enums\ReservationStatusEnum;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    
        public function definition(): array
    {
        return [
        'buyer_id' => User::factory()->buyer(),
        'status' => ReservationStatusEnum::Pending->value,
        'total_amount' => fake()->randomFloat(2, 10, 1000),
        'expires_at' => now()->addMinutes(15),
        'paid_at' => null,
        'refunded_at' => null,
    ];
  }

    /**
     * Pending reservation (awaiting payment).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => ReservationStatusEnum::Pending->value,
            'expires_at'  => now()->addMinutes(15),
            'paid_at'     => null,
            'refunded_at' => null,
        ]);
    }

    /**
     * Paid reservation.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => ReservationStatusEnum::Paid->value,
            'expires_at'  => null,
            'paid_at'     => now()->subMinutes(fake()->numberBetween(10, 10000)),
            'refunded_at' => null,
        ]);
    }

    /**
     * Refunded reservation.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => ReservationStatusEnum::Refunded->value,
            'expires_at'  => null,
            'paid_at'     => now()->subDays(fake()->numberBetween(3, 30)),
            'refunded_at' => now()->subDays(fake()->numberBetween(1, 2)),
        ]);
    }

    /**
     * Expired reservation.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => ReservationStatusEnum::Expired->value,
            'expires_at'  => now()->subMinutes(fake()->numberBetween(1, 1440)),
            'paid_at'     => null,
            'refunded_at' => null,
        ]);
    }
}

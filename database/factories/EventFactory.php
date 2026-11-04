<?php

namespace Database\Factories;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;


class EventFactory extends Factory
{
   
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+6 months');
        $endsAt   = fake()->dateTimeBetween($startsAt, (clone $startsAt)->modify('+8 hours'));

        return [
            'organizer_id' => User::factory()->organizer(),
            'venue_id'     => Venue::factory(),
            'title'        => fake()->sentence(4),
            'description'  => fake()->optional(0.8)->paragraphs(2, true),
            'event_type'   => fake()->randomElement(EventTypeEnum::cases())->value,
            'status'       => EventStatusEnum::Draft->value,
            'starts_at'    => $startsAt,
            'ends_at'      => $endsAt,
            'published_at' => null,
        ];
    }

    
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => EventStatusEnum::Draft->value,
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => EventStatusEnum::Published->value,
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => EventStatusEnum::Cancelled->value,
            'published_at' => now()->subDays(fake()->numberBetween(5, 60)),
        ]);
    }

  
    public function postponed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => EventStatusEnum::Postponed->value,
            'published_at' => now()->subDays(fake()->numberBetween(1, 15)),
        ]);
    }

    
    public function concert(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => EventTypeEnum::Concert->value,
        ]);
    }

   
    public function conference(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => EventTypeEnum::Conference->value,
        ]);
    }

   
    public function theater(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => EventTypeEnum::Theater->value,
        ]);
    }
}

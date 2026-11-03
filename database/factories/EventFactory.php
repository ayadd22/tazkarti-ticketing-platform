<?php

namespace Database\Factories;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
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

    /**
     * Set status to draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => EventStatusEnum::Draft->value,
            'published_at' => null,
        ]);
    }

    /**
     * Set status to published with a published_at timestamp.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => EventStatusEnum::Published->value,
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    /**
     * Set status to cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => EventStatusEnum::Cancelled->value,
            'published_at' => now()->subDays(fake()->numberBetween(5, 60)),
        ]);
    }

    /**
     * Set status to postponed.
     */
    public function postponed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => EventStatusEnum::Postponed->value,
            'published_at' => now()->subDays(fake()->numberBetween(1, 15)),
        ]);
    }

    /**
     * Set event type to concert.
     */
    public function concert(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => EventTypeEnum::Concert->value,
        ]);
    }

    /**
     * Set event type to conference.
     */
    public function conference(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => EventTypeEnum::Conference->value,
        ]);
    }

    /**
     * Set event type to theater.
     */
    public function theater(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => EventTypeEnum::Theater->value,
        ]);
    }
}

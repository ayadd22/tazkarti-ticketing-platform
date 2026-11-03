<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 *
 * Generates Media database attributes only.
 * No actual files are created on disk.
 *
 * Usage with a morph parent:
 *   MediaFactory::new()->for($event, 'mediable')->create(['collection' => 'poster']);
 */
class MediaFactory extends Factory
{
    /**
     * Available collection names per mediable type.
     */
    private const EVENT_COLLECTIONS  = ['poster', 'gallery', 'banner'];
    private const VENUE_COLLECTIONS  = ['gallery', 'logo', 'interior'];
    private const USER_COLLECTIONS   = ['logo', 'avatar'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $collection = fake()->randomElement(array_merge(
            self::EVENT_COLLECTIONS,
            self::VENUE_COLLECTIONS,
            self::USER_COLLECTIONS
        ));

        return [
            // mediable_id / mediable_type must be supplied via for() or explicit state
            'collection' => $collection,
            'path'       => $this->fakePath($collection),
            'alt_text'   => fake()->optional(0.7)->sentence(4),
        ];
    }

    /**
     * Scope to event poster collection.
     */
    public function poster(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection' => 'poster',
            'path'       => $this->fakePath('poster'),
        ]);
    }

    /**
     * Scope to gallery collection.
     */
    public function gallery(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection' => 'gallery',
            'path'       => $this->fakePath('gallery'),
        ]);
    }

    /**
     * Scope to logo collection.
     */
    public function logo(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection' => 'logo',
            'path'       => $this->fakePath('logo'),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Build a realistic-looking storage path for the given collection.
     * No file is actually created on disk.
     */
    private function fakePath(string $collection): string
    {
        $ext      = fake()->randomElement(['jpg', 'png', 'webp']);
        $filename = $collection . '_' . fake()->uuid() . '.' . $ext;

        return match ($collection) {
            'poster'   => 'events/posters/' . $filename,
            'gallery'  => 'gallery/' . $filename,
            'banner'   => 'events/banners/' . $filename,
            'logo'     => 'logos/' . $filename,
            'interior' => 'venues/interior/' . $filename,
            'avatar'   => 'avatars/' . $filename,
            default    => 'media/' . $filename,
        };
    }
}

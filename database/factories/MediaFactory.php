<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;


class MediaFactory extends Factory
{
   
    private const EVENT_COLLECTIONS  = ['poster', 'gallery', 'banner'];
    private const VENUE_COLLECTIONS  = ['gallery', 'logo', 'interior'];
    private const USER_COLLECTIONS   = ['logo', 'avatar'];

    
    public function definition(): array
    {
        $collection = fake()->randomElement(array_merge(
            self::EVENT_COLLECTIONS,
            self::VENUE_COLLECTIONS,
            self::USER_COLLECTIONS
        ));

        return [
           
            'collection' => $collection,
            'path'       => $this->fakePath($collection),
            'alt_text'   => fake()->optional(0.7)->sentence(4),
        ];
    }

   
    public function poster(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection' => 'poster',
            'path'       => $this->fakePath('poster'),
        ]);
    }

    
    public function gallery(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection' => 'gallery',
            'path'       => $this->fakePath('gallery'),
        ]);
    }

   
    public function logo(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection' => 'logo',
            'path'       => $this->fakePath('logo'),
        ]);
    }

    
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

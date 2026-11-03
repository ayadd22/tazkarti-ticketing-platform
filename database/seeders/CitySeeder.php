<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CitySeeder extends Seeder
{
    /**
     * Fixed set of cities used across all seeders.
     * Using firstOrCreate on slug guarantees idempotency.
     */
    public const CITIES = [
        'Cairo',
        'Alexandria',
        'Giza',
        'Luxor',
        'Aswan',
        'Hurghada',
        'Sharm El-Sheikh',
        'Port Said',
    ];

    public function run(): void
    {
        foreach (self::CITIES as $name) {
            City::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }

        $this->command->info('CitySeeder: ' . count(self::CITIES) . ' cities ensured.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VenueSeeder extends Seeder
{
    
    private const VENUES = [
        ['city' => 'Cairo',           'name' => 'Cairo International Conference Center', 'address' => '1 Corniche El-Nil, Cairo',            'capacity' => 10000],
        ['city' => 'Cairo',           'name' => 'Cairo Opera House',                     'address' => 'Gezira Island, Cairo',                'capacity' => 1200],
        ['city' => 'Cairo',           'name' => 'Cairo Stadium',                          'address' => 'Nasr City, Cairo',                    'capacity' => 75000],
        ['city' => 'Alexandria',      'name' => 'Bibliotheca Alexandrina',                'address' => 'El-Corniche, Alexandria',             'capacity' => 1800],
        ['city' => 'Alexandria',      'name' => 'Alexandria Sporting Club Arena',         'address' => 'Smouha, Alexandria',                  'capacity' => 8000],
        ['city' => 'Giza',            'name' => 'Sound and Light Theater',                'address' => 'Pyramids Road, Giza',                 'capacity' => 2500],
        ['city' => 'Luxor',           'name' => 'Luxor Temple Open-Air Stage',            'address' => 'Luxor Corniche, Luxor',               'capacity' => 3000],
        ['city' => 'Hurghada',        'name' => 'Hurghada Grand Aquarium Hall',           'address' => 'Dahar District, Hurghada',            'capacity' => 600],
        ['city' => 'Sharm El-Sheikh', 'name' => 'Sharm Convention Center',                'address' => 'Naama Bay, Sharm El-Sheikh',          'capacity' => 5000],
        ['city' => 'Port Said',       'name' => 'Port Said Cultural Palace',              'address' => 'El-Gomhoria St, Port Said',           'capacity' => 900],
    ];

    public function run(): void
    {
        $created = 0;

        foreach (self::VENUES as $data) {
            $city = City::where('slug', \Illuminate\Support\Str::slug($data['city']))->first();

            if (! $city) {
                $this->command->warn("VenueSeeder: city '{$data['city']}' not found — skipping venue '{$data['name']}'.");
                continue;
            }

            $venue = Venue::firstOrCreate(
                ['name' => $data['name']],
                [
                    'city_id'  => $city->id,
                    'address'  => $data['address'],
                    'capacity' => $data['capacity'],
                ],
            );

            if ($venue->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command->info("VenueSeeder: {$created} new venues created (" . count(self::VENUES) . ' total defined).');
    }
}

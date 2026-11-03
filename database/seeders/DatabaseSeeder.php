<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Dependency order:
     *   Cities → Venues → Users → Events → TicketTypes
     *   → BulkEvent (buyers + reservations + tickets)
     *   → Media
     *
     * All seeders are idempotent and safe to run multiple times.
     */
    public function run(): void
    {
        // Preserve the default test user created by the original scaffold
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => \Illuminate\Support\Facades\Hash::make('password')],
        );

        $this->call([
            CitySeeder::class,       // no dependencies
            VenueSeeder::class,      // depends on: cities
            UserSeeder::class,       // no dependencies
            EventSeeder::class,      // depends on: users (organizers), venues
            TicketTypeSeeder::class, // depends on: events
            BulkEventSeeder::class,  // depends on: users (organizer1), venues, events, ticket types
            MediaSeeder::class,      // depends on: events, venues, users
        ]);
    }
}

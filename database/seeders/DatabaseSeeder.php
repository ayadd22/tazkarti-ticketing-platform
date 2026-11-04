<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

  
    public function run(): void
    {
     
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => \Illuminate\Support\Facades\Hash::make('password')],
        );

        $this->call([
            CitySeeder::class,   
            VenueSeeder::class,   
            UserSeeder::class,  
            EventSeeder::class,    
            TicketTypeSeeder::class, 
            BulkEventSeeder::class, 
            MediaSeeder::class,      
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Seeder;

class TicketTypeSeeder extends Seeder
{

    private const TICKET_TYPES = [
        'Cairo Jazz Night 2027' => [
            ['name' => 'General Admission', 'price' => 150.00, 'total_quantity' => 800,  'max_per_order' => 6],
            ['name' => 'VIP',               'price' => 350.00, 'total_quantity' => 200,  'max_per_order' => 4],
        ],
        'Tech Summit Egypt 2027' => [
            ['name' => 'Standard',    'price' => 500.00,  'total_quantity' => 1500, 'max_per_order' => 5],
            ['name' => 'VIP',         'price' => 1200.00, 'total_quantity' => 300,  'max_per_order' => 2],
            ['name' => 'Early Bird',  'price' => 350.00,  'total_quantity' => 200,  'max_per_order' => 3],
        ],
        'Alexandria Literary Festival' => [
            ['name' => 'General Admission', 'price' => 80.00,  'total_quantity' => 1200, 'max_per_order' => 8],
            ['name' => 'Premium',           'price' => 200.00, 'total_quantity' => 150,  'max_per_order' => 4],
        ],
        'Luxor Heritage Night' => [
            ['name' => 'Standard', 'price' => 200.00, 'total_quantity' => 500, 'max_per_order' => 6],
            ['name' => 'VIP',      'price' => 500.00, 'total_quantity' => 100, 'max_per_order' => 2],
        ],
        'Sharm Startup Conference 2027' => [
            ['name' => 'Standard', 'price' => 750.00,  'total_quantity' => 400, 'max_per_order' => 4],
            ['name' => 'Startup',  'price' => 1500.00, 'total_quantity' => 50,  'max_per_order' => 2],
        ],
        'Mega Rock Festival 2026' => [
            ['name' => 'General Admission', 'price' => 250.00, 'total_quantity' => 20000, 'max_per_order' => 10],
        ],
        'Alexandria Music Week' => [
            ['name' => 'General Admission', 'price' => 120.00, 'total_quantity' => 3000, 'max_per_order' => 8],
            ['name' => 'Premium',           'price' => 300.00, 'total_quantity' => 500,  'max_per_order' => 4],
        ],
    ];

    public function run(): void
    {
        $created = 0;

        foreach (self::TICKET_TYPES as $eventTitle => $types) {
            $event = Event::where('title', $eventTitle)->first();

            if (! $event) {
                $this->command->warn("TicketTypeSeeder: event '{$eventTitle}' not found — skipping.");
                continue;
            }

            foreach ($types as $typeData) {
                $ticketType = TicketType::firstOrCreate(
                    [
                        'event_id' => $event->id,
                        'name'     => $typeData['name'],
                    ],
                    [
                        'price'             => $typeData['price'],
                        'total_quantity'    => $typeData['total_quantity'],
                        'reserved_quantity' => 0,
                        'sold_quantity'     => 0,
                        'max_per_order'     => $typeData['max_per_order'],
                    ],
                );

                if ($ticketType->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        $this->command->info("TicketTypeSeeder: {$created} new ticket types created.");
    }
}

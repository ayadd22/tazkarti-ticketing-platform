<?php

namespace Database\Seeders;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    
    private const FIXED_EVENTS = [
  
        [
            'organizer_email' => 'organizer1@tazkarti.test',
            'venue_name'      => 'Cairo Opera House',
            'title'           => 'Cairo Jazz Night 2027',
            'description'     => 'An evening of classic and contemporary jazz with Egypt\'s top musicians.',
            'event_type'      => EventTypeEnum::Concert,
            'status'          => EventStatusEnum::Published,
            'starts_at'       => '2027-03-15 20:00:00',
            'ends_at'         => '2027-03-15 23:30:00',
            'published_at'    => '2026-11-01 10:00:00',
        ],
        [
            'organizer_email' => 'organizer2@tazkarti.test',
            'venue_name'      => 'Cairo International Conference Center',
            'title'           => 'Tech Summit Egypt 2027',
            'description'     => 'The region\'s premier technology and innovation conference.',
            'event_type'      => EventTypeEnum::Conference,
            'status'          => EventStatusEnum::Published,
            'starts_at'       => '2027-04-10 09:00:00',
            'ends_at'         => '2027-04-11 18:00:00',
            'published_at'    => '2026-10-20 12:00:00',
        ],
        [
            'organizer_email' => 'organizer1@tazkarti.test',
            'venue_name'      => 'Bibliotheca Alexandrina',
            'title'           => 'Alexandria Literary Festival',
            'description'     => 'A weekend of readings, discussions, and performances celebrating Arabic literature.',
            'event_type'      => EventTypeEnum::Theater,
            'status'          => EventStatusEnum::Published,
            'starts_at'       => '2027-05-20 17:00:00',
            'ends_at'         => '2027-05-22 21:00:00',
            'published_at'    => '2026-11-02 09:00:00',
        ],
        // Draft events
        [
            'organizer_email' => 'organizer2@tazkarti.test',
            'venue_name'      => 'Luxor Temple Open-Air Stage',
            'title'           => 'Luxor Heritage Night',
            'description'     => 'An open-air concert under the stars at the Luxor Temple.',
            'event_type'      => EventTypeEnum::Concert,
            'status'          => EventStatusEnum::Draft,
            'starts_at'       => '2027-06-01 21:00:00',
            'ends_at'         => '2027-06-01 23:59:00',
            'published_at'    => null,
        ],
        [
            'organizer_email' => 'organizer1@tazkarti.test',
            'venue_name'      => 'Sharm Convention Center',
            'title'           => 'Sharm Startup Conference 2027',
            'description'     => 'Pitches, panels, and networking for the Red Sea startup ecosystem.',
            'event_type'      => EventTypeEnum::Conference,
            'status'          => EventStatusEnum::Draft,
            'starts_at'       => '2027-07-15 09:00:00',
            'ends_at'         => '2027-07-16 17:00:00',
            'published_at'    => null,
        ],
        // Cancelled event
        [
            'organizer_email' => 'organizer2@tazkarti.test',
            'venue_name'      => 'Cairo Stadium',
            'title'           => 'Mega Rock Festival 2026',
            'description'     => 'A large-scale rock festival. This event was cancelled.',
            'event_type'      => EventTypeEnum::Concert,
            'status'          => EventStatusEnum::Cancelled,
            'starts_at'       => '2026-09-01 18:00:00',
            'ends_at'         => '2026-09-01 23:00:00',
            'published_at'    => '2026-07-01 10:00:00',
        ],
       
        [
            'organizer_email' => 'organizer1@tazkarti.test',
            'venue_name'      => 'Alexandria Sporting Club Arena',
            'title'           => 'Alexandria Music Week',
            'description'     => 'A week of musical performances postponed from the original date.',
            'event_type'      => EventTypeEnum::Concert,
            'status'          => EventStatusEnum::Postponed,
            'starts_at'       => '2027-08-10 19:00:00',
            'ends_at'         => '2027-08-10 22:30:00',
            'published_at'    => '2026-10-01 11:00:00',
        ],
    ];

    public function run(): void
    {
        $created = 0;

        foreach (self::FIXED_EVENTS as $data) {
            $organizer = User::where('email', $data['organizer_email'])->first();
            $venue     = Venue::where('name', $data['venue_name'])->first();

            if (! $organizer || ! $venue) {
                $this->command->warn(
                    "EventSeeder: skipping '{$data['title']}' — organizer or venue not found."
                );
                continue;
            }

            $event = Event::firstOrCreate(
                [
                    'organizer_id' => $organizer->id,
                    'title'        => $data['title'],
                ],
                [
                    'venue_id'     => $venue->id,
                    'description'  => $data['description'],
                    'event_type'   => $data['event_type']->value,
                    'status'       => $data['status']->value,
                    'starts_at'    => $data['starts_at'],
                    'ends_at'      => $data['ends_at'],
                    'published_at' => $data['published_at'],
                ],
            );

            if ($event->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command->info(
            "EventSeeder: {$created} new events created (" . count(self::FIXED_EVENTS) . ' total defined).'
        );
    }
}

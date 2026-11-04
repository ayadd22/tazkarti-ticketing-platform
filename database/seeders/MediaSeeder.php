<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class MediaSeeder extends Seeder
{
  
    public function run(): void
    {
        $created = 0;
        $events = Event::all();

        foreach ($events as $event) {
            $path = 'events/posters/event_' . $event->id . '_poster.jpg';

            $media = Media::firstOrCreate(
                [
                    'mediable_type' => Event::class,
                    'mediable_id'   => $event->id,
                    'collection'    => 'poster',
                    'path'          => $path,
                ],
                [
                    'alt_text' => $event->title . ' poster',
                ],
            );

            if ($media->wasRecentlyCreated) {
                $created++;
            }

           
            if ($event->status->value === 'published') {
                $galleryPath = 'events/gallery/event_' . $event->id . '_gallery_1.jpg';

                $gallery = Media::firstOrCreate(
                    [
                        'mediable_type' => Event::class,
                        'mediable_id'   => $event->id,
                        'collection'    => 'gallery',
                        'path'          => $galleryPath,
                    ],
                    [
                        'alt_text' => $event->title . ' gallery image',
                    ],
                );

                if ($gallery->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

      
        $venues = Venue::all();

        foreach ($venues as $venue) {
            $path = 'venues/gallery/venue_' . $venue->id . '_1.jpg';

            $media = Media::firstOrCreate(
                [
                    'mediable_type' => Venue::class,
                    'mediable_id'   => $venue->id,
                    'collection'    => 'gallery',
                    'path'          => $path,
                ],
                [
                    'alt_text' => $venue->name . ' image',
                ],
            );

            if ($media->wasRecentlyCreated) {
                $created++;
            }
        }

        
        $organizers = User::where('role', 'organizer')->get();

        foreach ($organizers as $organizer) {
            $path = 'logos/organizer_' . $organizer->id . '_logo.png';

            $media = Media::firstOrCreate(
                [
                    'mediable_type' => User::class,
                    'mediable_id'   => $organizer->id,
                    'collection'    => 'logo',
                    'path'          => $path,
                ],
                [
                    'alt_text' => $organizer->name . ' logo',
                ],
            );

            if ($media->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command->info("MediaSeeder: {$created} new media records created.");
    }
}

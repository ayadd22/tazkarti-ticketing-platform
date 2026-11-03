<?php

namespace Tests\Feature;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Models\City;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventListingTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Create a published Event with a Venue that belongs to a City. */
    private function publishedEvent(array $overrides = []): Event
    {
        $city  = City::factory()->create();
        $venue = Venue::factory()->create(['city_id' => $city->id]);

        $base = [
            'organizer_id' => User::factory()->organizer()->create()->id,
            'venue_id'     => $venue->id,
            'title'        => 'Published Event',
            'description'  => 'A great event.',
            'event_type'   => EventTypeEnum::Concert->value,
            'status'       => EventStatusEnum::Published->value,
            'starts_at'    => now()->addDays(7),
            'ends_at'      => now()->addDays(7)->addHours(3),
            'published_at' => now()->subDay(),
        ];

        return Event::create(array_merge($base, $overrides));
    }

    /** Create a TicketType for the given event. */
    private function ticketType(Event $event, array $overrides = []): TicketType
    {
        return TicketType::create(array_merge([
            'event_id'          => $event->id,
            'name'              => 'General Admission',
            'price'             => 150.00,
            'total_quantity'    => 200,
            'reserved_quantity' => 10,
            'sold_quantity'     => 40,
            'max_per_order'     => 4,
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/events — listing
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function listing_returns_published_events(): void
    {
        $published = $this->publishedEvent(['title' => 'The Big Show']);

        $response = $this->getJson('/api/events');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.title', 'The Big Show')
            ->assertJsonPath('data.0.status', 'published');
    }

    #[Test]
    public function listing_excludes_draft_events(): void
    {
        $draft = Event::factory()->draft()->create();

        $response = $this->getJson('/api/events');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($draft->id, $ids);
    }

    #[Test]
    public function listing_excludes_cancelled_events(): void
    {
        $cancelled = Event::factory()->cancelled()->create();

        $response = $this->getJson('/api/events');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($cancelled->id, $ids);
    }

    #[Test]
    public function listing_excludes_postponed_events(): void
    {
        $postponed = Event::factory()->postponed()->create();

        $response = $this->getJson('/api/events');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($postponed->id, $ids);
    }

    #[Test]
    public function listing_is_paginated_with_default_page_size_of_15(): void
    {
        // Create 20 published events
        for ($i = 0; $i < 20; $i++) {
            $this->publishedEvent(['title' => "Event {$i}"]);
        }

        $response = $this->getJson('/api/events');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20);
    }

    #[Test]
    public function listing_respects_custom_per_page(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->publishedEvent(['title' => "Event {$i}"]);
        }

        $response = $this->getJson('/api/events?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5);
    }

    #[Test]
    public function listing_caps_per_page_at_50(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->publishedEvent(['title' => "Event {$i}"]);
        }

        $response = $this->getJson('/api/events?per_page=100');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 50);

        $this->assertCount(50, $response->json('data'));
    }

    #[Test]
    public function listing_returns_second_page(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->publishedEvent(['title' => "Event {$i}"]);
        }

        $response = $this->getJson('/api/events?per_page=15&page=2');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filters
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function filter_search_matches_title(): void
    {
        $match   = $this->publishedEvent(['title' => 'Cairo Jazz Night']);
        $noMatch = $this->publishedEvent(['title' => 'Tech Conference Summit']);

        $response = $this->getJson('/api/events?search=jazz');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($match->id, $ids);
        $this->assertNotContains($noMatch->id, $ids);
    }

    #[Test]
    public function filter_search_matches_description(): void
    {
        $match   = $this->publishedEvent(['title' => 'Night Out', 'description' => 'An outdoor concert under the stars']);
        $noMatch = $this->publishedEvent(['title' => 'Night Out', 'description' => 'A business summit for entrepreneurs']);

        $response = $this->getJson('/api/events?search=outdoor+concert');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($match->id, $ids);
        $this->assertNotContains($noMatch->id, $ids);
    }

    #[Test]
    public function filter_city_filters_by_slug(): void
    {
        $cairo     = City::factory()->create(['name' => 'Cairo',      'slug' => 'cairo']);
        $alex      = City::factory()->create(['name' => 'Alexandria', 'slug' => 'alexandria']);
        $venueCairo = Venue::factory()->create(['city_id' => $cairo->id]);
        $venueAlex  = Venue::factory()->create(['city_id' => $alex->id]);

        $cairoEvent = $this->publishedEvent(['venue_id' => $venueCairo->id, 'title' => 'Cairo Event']);
        $alexEvent  = $this->publishedEvent(['venue_id' => $venueAlex->id,  'title' => 'Alex Event']);

        $response = $this->getJson('/api/events?city=cairo');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($cairoEvent->id, $ids);
        $this->assertNotContains($alexEvent->id, $ids);
    }

    #[Test]
    public function filter_type_filters_by_event_type(): void
    {
        $concert    = $this->publishedEvent(['event_type' => EventTypeEnum::Concert->value,    'title' => 'Concert']);
        $conference = $this->publishedEvent(['event_type' => EventTypeEnum::Conference->value, 'title' => 'Conference']);

        $response = $this->getJson('/api/events?type=concert');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($concert->id, $ids);
        $this->assertNotContains($conference->id, $ids);
    }

    #[Test]
    public function filter_upcoming_excludes_past_events(): void
    {
        $future = $this->publishedEvent([
            'title'     => 'Future Event',
            'starts_at' => now()->addDays(5),
            'ends_at'   => now()->addDays(5)->addHours(2),
        ]);
        $past = $this->publishedEvent([
            'title'     => 'Past Event',
            'starts_at' => now()->subDays(3),
            'ends_at'   => now()->subDays(3)->addHours(2),
        ]);

        $response = $this->getJson('/api/events?upcoming=1');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($future->id, $ids);
        $this->assertNotContains($past->id, $ids);
    }

    #[Test]
    public function filters_can_be_combined(): void
    {
        // Published concert in Cairo, upcoming
        $target = $this->publishedEvent([
            'event_type' => EventTypeEnum::Concert->value,
            'title'      => 'Cairo Jazz',
            'starts_at'  => now()->addDays(5),
            'ends_at'    => now()->addDays(5)->addHours(2),
        ]);

        // Wrong type
        $this->publishedEvent([
            'event_type' => EventTypeEnum::Conference->value,
            'venue_id'   => $target->venue_id,
        ]);

        $response = $this->getJson('/api/events?type=concert&upcoming=1&search=Cairo');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($target->id, $ids);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Response structure
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function listing_includes_venue_and_city_data(): void
    {
        $city  = City::factory()->create(['name' => 'Cairo', 'slug' => 'cairo']);
        $venue = Venue::factory()->create(['city_id' => $city->id, 'name' => 'Cairo Arena']);
        $this->publishedEvent(['venue_id' => $venue->id, 'title' => 'Test Event']);

        $response = $this->getJson('/api/events');

        $response->assertOk()
            ->assertJsonPath('data.0.venue.name', 'Cairo Arena')
            ->assertJsonPath('data.0.venue.city.name', 'Cairo')
            ->assertJsonPath('data.0.venue.city.slug', 'cairo');
    }

    #[Test]
    public function listing_includes_ticket_types(): void
    {
        $event = $this->publishedEvent();
        $this->ticketType($event, ['name' => 'VIP', 'price' => 300.00]);

        $response = $this->getJson('/api/events');

        $response->assertOk()
            ->assertJsonPath('data.0.ticket_types.0.name', 'VIP')
            ->assertJsonPath('data.0.ticket_types.0.price', '300.00');
    }

    #[Test]
    public function listing_exposes_available_quantity_on_ticket_types(): void
    {
        $event = $this->publishedEvent();
        // total=200, sold=40, reserved=10 → available=150
        $this->ticketType($event, [
            'total_quantity'    => 200,
            'sold_quantity'     => 40,
            'reserved_quantity' => 10,
        ]);

        $response = $this->getJson('/api/events');

        $response->assertOk()
            ->assertJsonPath('data.0.ticket_types.0.available_quantity', 150);
    }

    #[Test]
    public function listing_does_not_expose_sensitive_fields(): void
    {
        $this->publishedEvent();

        $response = $this->getJson('/api/events');

        $response->assertOk();
        $event = $response->json('data.0');

        $this->assertArrayNotHasKey('organizer_id', $event);
        $this->assertArrayNotHasKey('venue_id', $event);
        $this->assertArrayNotHasKey('sold_quantity', $event['ticket_types'][0] ?? []);
        $this->assertArrayNotHasKey('reserved_quantity', $event['ticket_types'][0] ?? []);
    }

    #[Test]
    public function listing_does_not_expose_internal_ticket_type_inventory_fields(): void
    {
        $event = $this->publishedEvent();
        $this->ticketType($event);

        $response = $this->getJson('/api/events');

        $response->assertOk();
        $ticketType = $response->json('data.0.ticket_types.0');
        $this->assertArrayNotHasKey('sold_quantity', $ticketType);
        $this->assertArrayNotHasKey('reserved_quantity', $ticketType);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/events/{event} — show
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function show_returns_published_event(): void
    {
        $event = $this->publishedEvent(['title' => 'My Concert']);

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'title', 'description', 'event_type', 'status',
                'starts_at', 'ends_at', 'published_at', 'venue', 'ticket_types',
            ]])
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.title', 'My Concert')
            ->assertJsonPath('data.status', 'published');
    }

    #[Test]
    public function show_returns_venue_and_city(): void
    {
        $city  = City::factory()->create(['name' => 'Luxor', 'slug' => 'luxor']);
        $venue = Venue::factory()->create(['city_id' => $city->id, 'name' => 'Luxor Temple Stage']);
        $event = $this->publishedEvent(['venue_id' => $venue->id]);

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertOk()
            ->assertJsonPath('data.venue.name', 'Luxor Temple Stage')
            ->assertJsonPath('data.venue.city.name', 'Luxor')
            ->assertJsonPath('data.venue.city.slug', 'luxor');
    }

    #[Test]
    public function show_returns_ticket_types(): void
    {
        $event = $this->publishedEvent();
        $this->ticketType($event, ['name' => 'Premium', 'price' => 500.00]);

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertOk()
            ->assertJsonPath('data.ticket_types.0.name', 'Premium')
            ->assertJsonPath('data.ticket_types.0.price', '500.00');
    }

    #[Test]
    public function show_returns_404_for_draft_event(): void
    {
        $draft = Event::factory()->draft()->create();

        $response = $this->getJson("/api/events/{$draft->id}");

        $response->assertNotFound()
            ->assertJsonPath('message', 'Event not found.');
    }

    #[Test]
    public function show_returns_404_for_cancelled_event(): void
    {
        $cancelled = Event::factory()->cancelled()->create();

        $response = $this->getJson("/api/events/{$cancelled->id}");

        $response->assertNotFound();
    }

    #[Test]
    public function show_returns_404_for_postponed_event(): void
    {
        $postponed = Event::factory()->postponed()->create();

        $response = $this->getJson("/api/events/{$postponed->id}");

        $response->assertNotFound();
    }

    #[Test]
    public function show_returns_404_for_nonexistent_event(): void
    {
        $response = $this->getJson('/api/events/99999');

        $response->assertNotFound();
    }

    #[Test]
    public function show_does_not_expose_sensitive_fields(): void
    {
        $event = $this->publishedEvent();
        $this->ticketType($event);

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertArrayNotHasKey('organizer_id', $data);
        $this->assertArrayNotHasKey('venue_id', $data);

        $ticketType = $data['ticket_types'][0] ?? [];
        $this->assertArrayNotHasKey('sold_quantity', $ticketType);
        $this->assertArrayNotHasKey('reserved_quantity', $ticketType);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ordering
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function listing_is_ordered_by_starts_at_ascending(): void
    {
        $later  = $this->publishedEvent(['starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(10)->addHours(2)]);
        $sooner = $this->publishedEvent(['starts_at' => now()->addDays(2),  'ends_at' => now()->addDays(2)->addHours(2)]);

        $response = $this->getJson('/api/events');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->values();
        $this->assertEquals($sooner->id, $ids[0]);
        $this->assertEquals($later->id, $ids[1]);
    }
}

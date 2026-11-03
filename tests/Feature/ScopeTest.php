<?php

namespace Tests\Feature;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Enums\ReservationStatusEnum;
use App\Models\City;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScopeTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a minimal valid Event with sensible defaults, all overridable.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeEvent(array $overrides = []): Event
    {
        $organizer = $overrides['organizer_id'] ?? User::factory()->organizer()->create()->id;
        $venue     = $overrides['venue_id']     ?? Venue::factory()->create()->id;

        return Event::create(array_merge([
            'organizer_id' => $organizer,
            'venue_id'     => $venue,
            'title'        => 'Test Event',
            'description'  => null,
            'event_type'   => EventTypeEnum::Concert->value,
            'status'       => EventStatusEnum::Draft->value,
            'starts_at'    => now()->addDays(7),
            'ends_at'      => now()->addDays(7)->addHours(3),
            'published_at' => null,
        ], $overrides));
    }

    /**
     * Create a TicketType attached to the given event with sensible defaults.
     *
     * @param  Event  $event
     * @param  array<string, mixed>  $overrides
     */
    private function makeTicketType(Event $event, array $overrides = []): TicketType
    {
        return TicketType::create(array_merge([
            'event_id'          => $event->id,
            'name'              => 'General Admission',
            'price'             => 100.00,
            'total_quantity'    => 100,
            'reserved_quantity' => 0,
            'sold_quantity'     => 0,
            'max_per_order'     => 4,
        ], $overrides));
    }

    /**
     * Create a Reservation for the given buyer with sensible defaults.
     *
     * @param  User  $buyer
     * @param  array<string, mixed>  $overrides
     */
    private function makeReservation(User $buyer, array $overrides = []): Reservation
    {
        return Reservation::create(array_merge([
            'buyer_id'     => $buyer->id,
            'status'       => ReservationStatusEnum::Pending->value,
            'total_amount' => 100.00,
            'expires_at'   => now()->addMinutes(15),
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Event::scopePublished
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_published_returns_only_published_events(): void
    {
        $published = $this->makeEvent(['status' => EventStatusEnum::Published->value, 'published_at' => now()]);
        $draft     = $this->makeEvent(['status' => EventStatusEnum::Draft->value]);
        $cancelled = $this->makeEvent(['status' => EventStatusEnum::Cancelled->value, 'published_at' => now()]);
        $postponed = $this->makeEvent(['status' => EventStatusEnum::Postponed->value, 'published_at' => now()]);

        $results = Event::published()->pluck('id');

        $this->assertContains($published->id, $results);
        $this->assertNotContains($draft->id, $results);
        $this->assertNotContains($cancelled->id, $results);
        $this->assertNotContains($postponed->id, $results);
    }

    #[Test]
    public function scope_published_is_chainable_with_other_scopes(): void
    {
        // A published concert and a published conference
        $concert    = $this->makeEvent(['status' => EventStatusEnum::Published->value, 'event_type' => EventTypeEnum::Concert->value,    'published_at' => now()]);
        $conference = $this->makeEvent(['status' => EventStatusEnum::Published->value, 'event_type' => EventTypeEnum::Conference->value, 'published_at' => now()]);

        $results = Event::published()->ofType(EventTypeEnum::Concert)->pluck('id');

        $this->assertContains($concert->id, $results);
        $this->assertNotContains($conference->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Event::scopeUpcoming
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_upcoming_returns_only_future_events(): void
    {
        $future = $this->makeEvent(['starts_at' => now()->addDays(5),  'ends_at' => now()->addDays(5)->addHours(2)]);
        $past   = $this->makeEvent(['starts_at' => now()->subDays(5),  'ends_at' => now()->subDays(5)->addHours(2)]);

        $results = Event::upcoming()->pluck('id');

        $this->assertContains($future->id, $results);
        $this->assertNotContains($past->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Event::scopePast
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_past_returns_only_events_that_have_ended(): void
    {
        $ended   = $this->makeEvent(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
        $ongoing = $this->makeEvent(['starts_at' => now()->subHour(),  'ends_at' => now()->addHour()]);
        $future  = $this->makeEvent(['starts_at' => now()->addDay(),   'ends_at' => now()->addDay()->addHours(2)]);

        $results = Event::past()->pluck('id');

        $this->assertContains($ended->id, $results);
        $this->assertNotContains($ongoing->id, $results);
        $this->assertNotContains($future->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Event::scopeOfType
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_of_type_filters_by_enum_case(): void
    {
        $concert    = $this->makeEvent(['event_type' => EventTypeEnum::Concert->value]);
        $conference = $this->makeEvent(['event_type' => EventTypeEnum::Conference->value]);
        $theater    = $this->makeEvent(['event_type' => EventTypeEnum::Theater->value]);

        $results = Event::ofType(EventTypeEnum::Concert)->pluck('id');

        $this->assertContains($concert->id, $results);
        $this->assertNotContains($conference->id, $results);
        $this->assertNotContains($theater->id, $results);
    }

    #[Test]
    public function scope_of_type_also_accepts_a_plain_string(): void
    {
        $theater = $this->makeEvent(['event_type' => EventTypeEnum::Theater->value]);
        $concert = $this->makeEvent(['event_type' => EventTypeEnum::Concert->value]);

        $results = Event::ofType('theater')->pluck('id');

        $this->assertContains($theater->id, $results);
        $this->assertNotContains($concert->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Event::scopeInCity
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_in_city_filters_by_city_id(): void
    {
        $cairo = City::create(['name' => 'Cairo', 'slug' => 'cairo']);
        $alex  = City::create(['name' => 'Alexandria', 'slug' => 'alexandria']);

        $venueCairo = Venue::factory()->create(['city_id' => $cairo->id]);
        $venueAlex  = Venue::factory()->create(['city_id' => $alex->id]);

        $cairoEvent = $this->makeEvent(['venue_id' => $venueCairo->id]);
        $alexEvent  = $this->makeEvent(['venue_id' => $venueAlex->id]);

        $results = Event::inCity($cairo->id)->pluck('id');

        $this->assertContains($cairoEvent->id, $results);
        $this->assertNotContains($alexEvent->id, $results);
    }

    #[Test]
    public function scope_in_city_filters_by_city_slug(): void
    {
        $cairo = City::create(['name' => 'Cairo', 'slug' => 'cairo']);
        $alex  = City::create(['name' => 'Alexandria', 'slug' => 'alexandria']);

        $venueCairo = Venue::factory()->create(['city_id' => $cairo->id]);
        $venueAlex  = Venue::factory()->create(['city_id' => $alex->id]);

        $cairoEvent = $this->makeEvent(['venue_id' => $venueCairo->id]);
        $alexEvent  = $this->makeEvent(['venue_id' => $venueAlex->id]);

        $results = Event::inCity('cairo')->pluck('id');

        $this->assertContains($cairoEvent->id, $results);
        $this->assertNotContains($alexEvent->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Event::scopeSearch
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_search_matches_title(): void
    {
        $match   = $this->makeEvent(['title' => 'Cairo Jazz Night']);
        $noMatch = $this->makeEvent(['title' => 'Tech Conference']);

        $results = Event::search('jazz')->pluck('id');

        $this->assertContains($match->id, $results);
        $this->assertNotContains($noMatch->id, $results);
    }

    #[Test]
    public function scope_search_matches_description(): void
    {
        $match   = $this->makeEvent(['title' => 'Generic Title', 'description' => 'A wonderful outdoor concert under the stars']);
        $noMatch = $this->makeEvent(['title' => 'Generic Title', 'description' => 'A professional business summit']);

        $results = Event::search('outdoor concert')->pluck('id');

        $this->assertContains($match->id, $results);
        $this->assertNotContains($noMatch->id, $results);
    }

    #[Test]
    public function scope_search_is_case_insensitive(): void
    {
        $event = $this->makeEvent(['title' => 'Cairo Jazz Night']);

        // Uppercase search should still find the event
        $results = Event::search('CAIRO')->pluck('id');

        $this->assertContains($event->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Event::scopeForOrganizer
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_for_organizer_returns_only_that_organizers_events(): void
    {
        $org1 = User::factory()->organizer()->create();
        $org2 = User::factory()->organizer()->create();

        $venue = Venue::factory()->create();

        $event1 = $this->makeEvent(['organizer_id' => $org1->id, 'venue_id' => $venue->id]);
        $event2 = $this->makeEvent(['organizer_id' => $org2->id, 'venue_id' => $venue->id]);

        // scope works regardless of status — organizer can see all their events
        $results = Event::forOrganizer($org1->id)->pluck('id');

        $this->assertContains($event1->id, $results);
        $this->assertNotContains($event2->id, $results);
    }

    #[Test]
    public function scope_for_organizer_returns_all_statuses(): void
    {
        $org = User::factory()->organizer()->create();

        $draft     = $this->makeEvent(['organizer_id' => $org->id, 'status' => EventStatusEnum::Draft->value]);
        $published = $this->makeEvent(['organizer_id' => $org->id, 'status' => EventStatusEnum::Published->value, 'published_at' => now()]);
        $cancelled = $this->makeEvent(['organizer_id' => $org->id, 'status' => EventStatusEnum::Cancelled->value, 'published_at' => now()]);

        $results = Event::forOrganizer($org->id)->pluck('id');

        // Management scope sees every status — not filtered to published only
        $this->assertContains($draft->id, $results);
        $this->assertContains($published->id, $results);
        $this->assertContains($cancelled->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TicketType::scopeAvailable
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_available_returns_ticket_types_with_remaining_inventory(): void
    {
        $event = $this->makeEvent();

        $available   = $this->makeTicketType($event, ['total_quantity' => 100, 'sold_quantity' => 40, 'reserved_quantity' => 10]);
        $soldOut     = $this->makeTicketType($event, ['total_quantity' => 100, 'sold_quantity' => 100, 'reserved_quantity' => 0]);
        $reservedOut = $this->makeTicketType($event, ['total_quantity' => 50,  'sold_quantity' => 0,  'reserved_quantity' => 50]);

        $results = TicketType::available()->pluck('id');

        $this->assertContains($available->id, $results);
        $this->assertNotContains($soldOut->id, $results);
        $this->assertNotContains($reservedOut->id, $results);
    }

    #[Test]
    public function scope_available_treats_reserved_slots_as_taken(): void
    {
        $event = $this->makeEvent();

        // total=10, sold=5, reserved=5 → 5+5=10 = total → no inventory left
        $exactlyFull = $this->makeTicketType($event, [
            'total_quantity'    => 10,
            'sold_quantity'     => 5,
            'reserved_quantity' => 5,
        ]);

        // total=10, sold=5, reserved=4 → 5+4=9 < 10 → one slot available
        $oneLeft = $this->makeTicketType($event, [
            'total_quantity'    => 10,
            'sold_quantity'     => 5,
            'reserved_quantity' => 4,
        ]);

        $results = TicketType::available()->pluck('id');

        $this->assertNotContains($exactlyFull->id, $results);
        $this->assertContains($oneLeft->id, $results);
    }

    #[Test]
    public function scope_available_is_usable_on_an_event_relationship(): void
    {
        $event = $this->makeEvent();

        $available = $this->makeTicketType($event, ['total_quantity' => 50, 'sold_quantity' => 10, 'reserved_quantity' => 5]);
        $soldOut   = $this->makeTicketType($event, ['total_quantity' => 50, 'sold_quantity' => 50, 'reserved_quantity' => 0]);

        $results = $event->ticketTypes()->available()->pluck('id');

        $this->assertContains($available->id, $results);
        $this->assertNotContains($soldOut->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TicketType::scopeWithinPriceRange
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_within_price_range_filters_by_min_and_max(): void
    {
        $event = $this->makeEvent();

        $cheap     = $this->makeTicketType($event, ['name' => 'Cheap',     'price' => 20.00]);
        $mid       = $this->makeTicketType($event, ['name' => 'Mid',       'price' => 100.00]);
        $expensive = $this->makeTicketType($event, ['name' => 'Expensive', 'price' => 500.00]);

        $results = TicketType::withinPriceRange(50, 200)->pluck('id');

        $this->assertNotContains($cheap->id, $results);
        $this->assertContains($mid->id, $results);
        $this->assertNotContains($expensive->id, $results);
    }

    #[Test]
    public function scope_within_price_range_accepts_null_bounds(): void
    {
        $event = $this->makeEvent();

        $cheap     = $this->makeTicketType($event, ['name' => 'Cheap',     'price' => 20.00]);
        $expensive = $this->makeTicketType($event, ['name' => 'Expensive', 'price' => 500.00]);

        // null max → only min applies
        $results = TicketType::withinPriceRange(100, null)->pluck('id');
        $this->assertNotContains($cheap->id, $results);
        $this->assertContains($expensive->id, $results);

        // null min → only max applies
        $results = TicketType::withinPriceRange(null, 50)->pluck('id');
        $this->assertContains($cheap->id, $results);
        $this->assertNotContains($expensive->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reservation::scopePending
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_pending_returns_only_pending_reservations(): void
    {
        $buyer = User::factory()->buyer()->create();

        $pending  = $this->makeReservation($buyer, ['status' => ReservationStatusEnum::Pending->value]);
        $paid     = $this->makeReservation($buyer, ['status' => ReservationStatusEnum::Paid->value,     'expires_at' => null, 'paid_at' => now()]);
        $refunded = $this->makeReservation($buyer, ['status' => ReservationStatusEnum::Refunded->value, 'expires_at' => null, 'paid_at' => now()->subDay(), 'refunded_at' => now()]);
        $expired  = $this->makeReservation($buyer, ['status' => ReservationStatusEnum::Expired->value,  'expires_at' => now()->subHour()]);

        $results = Reservation::pending()->pluck('id');

        $this->assertContains($pending->id, $results);
        $this->assertNotContains($paid->id, $results);
        $this->assertNotContains($refunded->id, $results);
        $this->assertNotContains($expired->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reservation::scopePaid
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_paid_returns_only_paid_reservations(): void
    {
        $buyer = User::factory()->buyer()->create();

        $paid    = $this->makeReservation($buyer, ['status' => ReservationStatusEnum::Paid->value,    'expires_at' => null, 'paid_at' => now()]);
        $pending = $this->makeReservation($buyer, ['status' => ReservationStatusEnum::Pending->value]);

        $results = Reservation::paid()->pluck('id');

        $this->assertContains($paid->id, $results);
        $this->assertNotContains($pending->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reservation::scopeRefunded
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_refunded_returns_only_refunded_reservations(): void
    {
        $buyer = User::factory()->buyer()->create();

        $refunded = $this->makeReservation($buyer, [
            'status'      => ReservationStatusEnum::Refunded->value,
            'expires_at'  => null,
            'paid_at'     => now()->subDay(),
            'refunded_at' => now(),
        ]);
        $paid = $this->makeReservation($buyer, ['status' => ReservationStatusEnum::Paid->value, 'expires_at' => null, 'paid_at' => now()]);

        $results = Reservation::refunded()->pluck('id');

        $this->assertContains($refunded->id, $results);
        $this->assertNotContains($paid->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reservation::scopeExpiredAt
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_expired_at_returns_pending_reservations_past_their_expiry(): void
    {
        $buyer = User::factory()->buyer()->create();

        $stale = $this->makeReservation($buyer, [
            'status'     => ReservationStatusEnum::Pending->value,
            'expires_at' => now()->subMinutes(30),
        ]);
        $fresh = $this->makeReservation($buyer, [
            'status'     => ReservationStatusEnum::Pending->value,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Use the combined chain the expiry job would use
        $results = Reservation::pending()->expiredAt()->pluck('id');

        $this->assertContains($stale->id, $results);
        $this->assertNotContains($fresh->id, $results);
    }

    #[Test]
    public function scope_expired_at_accepts_a_custom_point_in_time(): void
    {
        $buyer = User::factory()->buyer()->create();

        $r = $this->makeReservation($buyer, [
            'status'     => ReservationStatusEnum::Pending->value,
            'expires_at' => now()->subDay(), // expired yesterday
        ]);

        // Check "as of" 2 hours ago — reservation is still expired relative to that point
        $results = Reservation::expiredAt(now()->subHours(2))->pluck('id');
        $this->assertContains($r->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reservation::scopeForBuyer
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function scope_for_buyer_returns_only_that_buyers_reservations(): void
    {
        $buyer1 = User::factory()->buyer()->create();
        $buyer2 = User::factory()->buyer()->create();

        $r1 = $this->makeReservation($buyer1);
        $r2 = $this->makeReservation($buyer2);

        $results = Reservation::forBuyer($buyer1->id)->pluck('id');

        $this->assertContains($r1->id, $results);
        $this->assertNotContains($r2->id, $results);
    }

    #[Test]
    public function scope_for_buyer_is_chainable_with_status_scopes(): void
    {
        $buyer = User::factory()->buyer()->create();

        $paid    = $this->makeReservation($buyer, ['status' => ReservationStatusEnum::Paid->value,    'expires_at' => null, 'paid_at' => now()]);
        $pending = $this->makeReservation($buyer, ['status' => ReservationStatusEnum::Pending->value]);

        $results = Reservation::paid()->forBuyer($buyer->id)->pluck('id');

        $this->assertContains($paid->id, $results);
        $this->assertNotContains($pending->id, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // No Global Scope: management queries retain full access
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function unscoped_event_query_returns_all_statuses(): void
    {
        $draft     = $this->makeEvent(['status' => EventStatusEnum::Draft->value]);
        $published = $this->makeEvent(['status' => EventStatusEnum::Published->value, 'published_at' => now()]);
        $cancelled = $this->makeEvent(['status' => EventStatusEnum::Cancelled->value, 'published_at' => now()]);
        $postponed = $this->makeEvent(['status' => EventStatusEnum::Postponed->value, 'published_at' => now()]);

        $results = Event::pluck('id');

        // Admins and organizers querying Event::all() must see every record
        $this->assertContains($draft->id, $results);
        $this->assertContains($published->id, $results);
        $this->assertContains($cancelled->id, $results);
        $this->assertContains($postponed->id, $results);
    }
}

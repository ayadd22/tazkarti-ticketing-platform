<?php

namespace Tests\Feature;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Enums\ReservationStatusEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a published event with a fresh venue/city chain via factories.
     */
    private function publishedEvent(array $overrides = []): Event
    {
        return Event::factory()
            ->published()
            ->create($overrides);
    }

    /**
     * Create a TicketType for the given event with known inventory values.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function ticketType(Event $event, array $overrides = []): TicketType
    {
        return TicketType::factory()->create(array_merge([
            'event_id'          => $event->id,
            'price'             => '100.00',
            'total_quantity'    => 50,
            'reserved_quantity' => 0,
            'sold_quantity'     => 0,
            'max_per_order'     => 10,
        ], $overrides));
    }

    /**
     * Return a Sanctum token header for the given user.
     */
    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Authentication & authorization guards
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function guest_cannot_create_a_reservation(): void
    {
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event);

        $response = $this->postJson('/api/reservations', [
            'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function organizer_cannot_create_a_reservation(): void
    {
        $organizer  = User::factory()->organizer()->create();
        $event      = $this->publishedEvent(['organizer_id' => $organizer->id]);
        $ticketType = $this->ticketType($event);

        $response = $this->withToken($this->tokenFor($organizer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ]);

        // StoreReservationRequest::authorize() → ReservationPolicy::create() → buyer only → 403
        $response->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Successful reservation creation
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function buyer_can_create_a_reservation_with_a_single_item(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event, ['price' => '150.00', 'total_quantity' => 20, 'max_per_order' => 5]);

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 2]],
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id', 'status', 'total_amount', 'expires_at', 'created_at',
                    'items', 'tickets',
                ],
            ])
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_amount', '300.00')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonCount(2, 'data.tickets');

        // Reservation exists in the DB
        $this->assertDatabaseHas('reservations', [
            'buyer_id'     => $buyer->id,
            'status'       => ReservationStatusEnum::Pending->value,
            'total_amount' => '300.00',
        ]);

        // Exactly 1 ReservationItem with the correct snapshot price
        $this->assertDatabaseHas('reservation_items', [
            'ticket_type_id' => $ticketType->id,
            'quantity'        => 2,
            'unit_price'      => '150.00',
        ]);

        // 2 tickets created with pending status (payment not yet confirmed)
        $this->assertDatabaseCount('tickets', 2);
        $this->assertDatabaseHas('tickets', [
            'ticket_type_id' => $ticketType->id,
            'status'          => TicketStatusEnum::Pending->value,
        ]);
    }

    #[Test]
    public function buyer_can_create_a_reservation_with_multiple_items(): void
    {
        $buyer = User::factory()->buyer()->create();
        $event = $this->publishedEvent();
        $typeA = $this->ticketType($event, ['price' => '100.00', 'max_per_order' => 5]);
        $typeB = $this->ticketType($event, ['price' => '250.00', 'max_per_order' => 3]);

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [
                    ['ticket_type_id' => $typeA->id, 'quantity' => 2],
                    ['ticket_type_id' => $typeB->id, 'quantity' => 1],
                ],
            ]);

        // total = (100 × 2) + (250 × 1) = 450
        $response->assertCreated()
            ->assertJsonPath('data.total_amount', '450.00')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonCount(3, 'data.tickets');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Duplicate ticket_type_id merging
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function duplicate_ticket_type_ids_are_merged_by_summing_quantities(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event, ['price' => '100.00', 'max_per_order' => 10, 'total_quantity' => 20]);

        // Send the same ticket_type_id twice (quantities 2 + 3 = 5)
        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [
                    ['ticket_type_id' => $ticketType->id, 'quantity' => 2],
                    ['ticket_type_id' => $ticketType->id, 'quantity' => 3],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.total_amount', '500.00')
            ->assertJsonCount(1, 'data.items')   // merged → single ReservationItem
            ->assertJsonCount(5, 'data.tickets'); // 5 tickets total

        // DB unique constraint satisfied — exactly one item row
        $this->assertDatabaseCount('reservation_items', 1);
        $this->assertDatabaseHas('reservation_items', [
            'ticket_type_id' => $ticketType->id,
            'quantity'        => 5,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Inventory counter correctness
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function reserved_quantity_is_incremented_after_successful_reservation(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event, ['reserved_quantity' => 5]);

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 3]],
            ])
            ->assertCreated();

        // reserved_quantity should now be 5 + 3 = 8
        $this->assertDatabaseHas('ticket_types', [
            'id'                => $ticketType->id,
            'reserved_quantity' => 8,
        ]);
        // sold_quantity must remain unchanged
        $this->assertDatabaseHas('ticket_types', [
            'id'           => $ticketType->id,
            'sold_quantity' => 0,
        ]);
    }

    #[Test]
    public function total_amount_is_computed_from_database_prices_not_client_input(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event, ['price' => '200.00', 'max_per_order' => 5]);

        // Client sends no price — price comes from the DB ticket type
        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 3]],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.total_amount', '600.00'); // 200 × 3, never from client
    }

    #[Test]
    public function unit_price_in_reservation_item_is_snapshotted_from_db(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event, ['price' => '175.50', 'max_per_order' => 5]);

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('reservation_items', [
            'ticket_type_id' => $ticketType->id,
            'unit_price'      => '175.50',
        ]);
    }

    #[Test]
    public function expires_at_is_set_in_the_future(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event);

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ]);

        $response->assertCreated();

        $expiresAt = $response->json('data.expires_at');
        $this->assertNotNull($expiresAt);
        $this->assertTrue(now()->lt(\Carbon\Carbon::parse($expiresAt)));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Inventory validation failures
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function reservation_fails_when_requested_quantity_exceeds_available_inventory(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event, [
            'total_quantity'    => 5,
            'sold_quantity'     => 3,
            'reserved_quantity' => 2,
            'max_per_order'     => 10,  // per-order limit is fine; inventory is the blocker
        ]);
        // available = 5 - 3 - 2 = 0

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ]);

        $response->assertUnprocessable();

        // No reservation or tickets created
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('tickets', 0);

        // Counter unchanged
        $this->assertDatabaseHas('ticket_types', [
            'id'                => $ticketType->id,
            'reserved_quantity' => 2,
        ]);
    }

    #[Test]
    public function reservation_fails_when_quantity_exceeds_max_per_order(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event, [
            'total_quantity'    => 50,
            'reserved_quantity' => 0,
            'sold_quantity'     => 0,
            'max_per_order'     => 3,
        ]);

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 4]],
            ]);

        $response->assertUnprocessable();

        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseHas('ticket_types', [
            'id'                => $ticketType->id,
            'reserved_quantity' => 0,
        ]);
    }

    #[Test]
    public function all_changes_are_rolled_back_when_any_item_fails_validation(): void
    {
        $buyer  = User::factory()->buyer()->create();
        $event  = $this->publishedEvent();
        $typeOk = $this->ticketType($event, ['total_quantity' => 20, 'max_per_order' => 5]);
        $typeBad = $this->ticketType($event, [
            'total_quantity'    => 2,
            'reserved_quantity' => 2,  // fully reserved → available = 0
            'max_per_order'     => 5,
        ]);

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [
                    ['ticket_type_id' => $typeOk->id,  'quantity' => 1],
                    ['ticket_type_id' => $typeBad->id, 'quantity' => 1],
                ],
            ]);

        $response->assertUnprocessable();

        // Nothing persisted — the transaction rolled back
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('reservation_items', 0);
        $this->assertDatabaseCount('tickets', 0);

        // The "good" type must not have had its counter incremented
        $this->assertDatabaseHas('ticket_types', [
            'id'                => $typeOk->id,
            'reserved_quantity' => 0,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Input validation (FormRequest layer)
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function reservation_fails_when_items_array_is_empty(): void
    {
        $buyer = User::factory()->buyer()->create();

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', ['items' => []]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    #[Test]
    public function reservation_fails_when_ticket_type_does_not_exist(): void
    {
        $buyer = User::factory()->buyer()->create();

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => 99999, 'quantity' => 1]],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.ticket_type_id']);
    }

    #[Test]
    public function extra_keys_in_items_are_silently_stripped(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event, ['price' => '100.00']);

        // The client sends a rogue 'price' key — it must be ignored
        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [[
                    'ticket_type_id' => $ticketType->id,
                    'quantity'        => 1,
                    'price'           => '0.01',   // rogue field
                    'buyer_id'        => 999,       // rogue field
                ]],
            ]);

        // Reservation succeeds and uses the DB price, not the client price
        $response->assertCreated()
            ->assertJsonPath('data.total_amount', '100.00');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Response structure — sensitive fields must not be exposed
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function response_does_not_expose_buyer_id(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event);

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ]);

        $response->assertCreated();
        $this->assertArrayNotHasKey('buyer_id', $response->json('data'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Listing — GET /api/reservations
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function buyer_can_list_their_own_reservations(): void
    {
        $buyer = User::factory()->buyer()->create();
        Reservation::factory()->count(3)->create(['buyer_id' => $buyer->id]);

        $response = $this->withToken($this->tokenFor($buyer))
            ->getJson('/api/reservations');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    public function buyer_does_not_see_another_buyers_reservations(): void
    {
        $buyerA = User::factory()->buyer()->create();
        $buyerB = User::factory()->buyer()->create();

        Reservation::factory()->count(2)->create(['buyer_id' => $buyerA->id]);
        Reservation::factory()->count(3)->create(['buyer_id' => $buyerB->id]);

        // buyerA should only see their 2 reservations
        $response = $this->withToken($this->tokenFor($buyerA))
            ->getJson('/api/reservations');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);

        $returnedIds = collect($response->json('data'))->pluck('id');
        $buyerBIds   = Reservation::where('buyer_id', $buyerB->id)->pluck('id');
        foreach ($buyerBIds as $id) {
            $this->assertNotContains($id, $returnedIds);
        }
    }

    #[Test]
    public function guest_cannot_list_reservations(): void
    {
        $this->getJson('/api/reservations')->assertUnauthorized();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show — GET /api/reservations/{reservation}
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function buyer_can_view_their_own_reservation(): void
    {
        $buyer       = User::factory()->buyer()->create();
        $reservation = Reservation::factory()->create(['buyer_id' => $buyer->id]);

        $response = $this->withToken($this->tokenFor($buyer))
            ->getJson("/api/reservations/{$reservation->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $reservation->id);
    }

    #[Test]
    public function buyer_cannot_view_another_buyers_reservation(): void
    {
        $buyerA      = User::factory()->buyer()->create();
        $buyerB      = User::factory()->buyer()->create();
        $reservation = Reservation::factory()->create(['buyer_id' => $buyerB->id]);

        $response = $this->withToken($this->tokenFor($buyerA))
            ->getJson("/api/reservations/{$reservation->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function admin_can_view_any_reservation(): void
    {
        $admin       = User::factory()->admin()->create();
        $buyer       = User::factory()->buyer()->create();
        $reservation = Reservation::factory()->create(['buyer_id' => $buyer->id]);

        $response = $this->withToken($this->tokenFor($admin))
            ->getJson("/api/reservations/{$reservation->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $reservation->id);
    }

    #[Test]
    public function guest_cannot_view_a_reservation(): void
    {
        $buyer       = User::factory()->buyer()->create();
        $reservation = Reservation::factory()->create(['buyer_id' => $buyer->id]);

        $this->getJson("/api/reservations/{$reservation->id}")
            ->assertUnauthorized();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ticket lifecycle — pending status on creation
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function tickets_created_for_pending_reservations_have_pending_status(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event);

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 3]],
            ])
            ->assertCreated();

        $this->assertDatabaseCount('tickets', 3);

        // Every ticket must be pending — none should be valid
        $this->assertDatabaseHas('tickets', [
            'ticket_type_id' => $ticketType->id,
            'status'          => TicketStatusEnum::Pending->value,
        ]);
        $this->assertDatabaseMissing('tickets', [
            'status' => TicketStatusEnum::Valid->value,
        ]);
    }

    #[Test]
    public function ticket_factory_pending_state_creates_pending_tickets(): void
    {
        $ticket = \App\Models\Ticket::factory()->pending()->create();

        $this->assertSame(TicketStatusEnum::Pending, $ticket->status);
        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => TicketStatusEnum::Pending->value,
        ]);
    }

    #[Test]
    public function ticket_factory_default_state_creates_valid_tickets(): void
    {
        // The factory default (used for seeded paid tickets) must remain Valid
        $ticket = \App\Models\Ticket::factory()->create();

        $this->assertSame(TicketStatusEnum::Valid, $ticket->status);
        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => TicketStatusEnum::Valid->value,
        ]);
    }
}

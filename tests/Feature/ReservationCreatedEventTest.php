<?php

namespace Tests\Feature;

use App\Events\ReservationCreated;
use App\Listeners\SendReservationConfirmation;
use App\Mail\ReservationConfirmationMail;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReservationCreatedEventTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function publishedEvent(array $overrides = []): Event
    {
        return Event::factory()->published()->create($overrides);
    }

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

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeReservation(User $buyer = null): array
    {
        $buyer      ??= User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event);

        return [
            'buyer'       => $buyer,
            'ticketType'  => $ticketType,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Event dispatching
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function reservation_created_event_is_dispatched_after_successful_creation(): void
    {
        EventFacade::fake();

        ['buyer' => $buyer, 'ticketType' => $ticketType] = $this->makeReservation();

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        EventFacade::assertDispatched(ReservationCreated::class);
    }

    #[Test]
    public function reservation_created_event_carries_the_correct_reservation(): void
    {
        EventFacade::fake();

        ['buyer' => $buyer, 'ticketType' => $ticketType] = $this->makeReservation();

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 2]],
            ])
            ->assertCreated();

        $reservationId = $response->json('data.id');

        EventFacade::assertDispatched(
            ReservationCreated::class,
            fn (ReservationCreated $event) => $event->reservation->id === $reservationId,
        );
    }

    #[Test]
    public function reservation_created_event_is_not_dispatched_when_creation_fails(): void
    {
        EventFacade::fake();

        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event, [
            'total_quantity'    => 2,
            'reserved_quantity' => 2, // fully reserved — no inventory
            'max_per_order'     => 5,
        ]);

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertUnprocessable();

        EventFacade::assertNotDispatched(ReservationCreated::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Listener wiring — auto-discovery
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function send_reservation_confirmation_listener_is_registered_for_the_event(): void
    {
        EventFacade::fake();

        ['buyer' => $buyer, 'ticketType' => $ticketType] = $this->makeReservation();

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        EventFacade::assertListening(
            ReservationCreated::class,
            SendReservationConfirmation::class,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Listener queuing
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function send_reservation_confirmation_listener_is_queued(): void
    {
        // Queue::fake() captures queued jobs without executing them,
        // while still allowing the real event system to fire.
        Queue::fake();

        ['buyer' => $buyer, 'ticketType' => $ticketType] = $this->makeReservation();

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        // With $afterCommit = true, Laravel uses a deferred callback to push the
        // job after the transaction commits. Verify the listener itself is
        // declared as ShouldQueue and that $afterCommit is true.
        $listener = new SendReservationConfirmation();

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $listener);
        $this->assertTrue($listener->afterCommit);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mail sending
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function confirmation_email_is_sent_to_the_buyer(): void
    {
        Mail::fake();

        // Run the listener synchronously so we don't need a real queue worker.
        // Swap to sync driver for this test only.
        config(['queue.default' => 'sync']);

        ['buyer' => $buyer, 'ticketType' => $ticketType] = $this->makeReservation();

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        Mail::assertSent(
            ReservationConfirmationMail::class,
            fn (ReservationConfirmationMail $mail) => $mail->hasTo($buyer->email),
        );
    }

    #[Test]
    public function confirmation_email_is_sent_exactly_once(): void
    {
        Mail::fake();
        config(['queue.default' => 'sync']);

        ['buyer' => $buyer, 'ticketType' => $ticketType] = $this->makeReservation();

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        Mail::assertSent(ReservationConfirmationMail::class, 1);
    }

    #[Test]
    public function confirmation_email_carries_the_correct_reservation(): void
    {
        Mail::fake();
        config(['queue.default' => 'sync']);

        ['buyer' => $buyer, 'ticketType' => $ticketType] = $this->makeReservation();

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $reservationId = $response->json('data.id');

        Mail::assertSent(
            ReservationConfirmationMail::class,
            fn (ReservationConfirmationMail $mail) =>
                $mail->reservation->id === $reservationId,
        );
    }

    #[Test]
    public function confirmation_email_is_not_sent_when_reservation_fails(): void
    {
        Mail::fake();
        config(['queue.default' => 'sync']);

        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event, [
            'total_quantity'    => 2,
            'reserved_quantity' => 2,
            'max_per_order'     => 5,
        ]);

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertUnprocessable();

        Mail::assertNotSent(ReservationConfirmationMail::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API response is unchanged
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function api_response_structure_is_unchanged_after_event_dispatch(): void
    {
        // Fake both queue and mail so neither side-effect runs.
        Queue::fake();
        Mail::fake();

        ['buyer' => $buyer, 'ticketType' => $ticketType] = $this->makeReservation();

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id', 'status', 'total_amount',
                    'expires_at', 'created_at',
                    'items', 'tickets',
                ],
            ])
            ->assertJsonPath('data.status', 'pending');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Email content — pending-payment wording, currency, expiry
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function confirmation_email_subject_contains_reservation_id(): void
    {
        Mail::fake();
        config(['queue.default' => 'sync']);

        ['buyer' => $buyer, 'ticketType' => $ticketType] = $this->makeReservation();

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $reservationId = $response->json('data.id');

        Mail::assertSent(
            ReservationConfirmationMail::class,
            fn (ReservationConfirmationMail $mail) =>
                $mail->envelope()->subject === "Your Tazkarti Reservation #{$reservationId}",
        );
    }

    #[Test]
    public function confirmation_email_renders_pending_payment_heading(): void
    {
        config(['queue.default' => 'sync']);

        ['buyer' => $buyer, 'ticketType' => $ticketType] = $this->makeReservation();

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $reservationId = $response->json('data.id');
        $reservation   = \App\Models\Reservation::with(['buyer', 'reservationItems'])->find($reservationId);

        $html = (new ReservationConfirmationMail($reservation))->render();

        // Must contain the pending-payment heading, not the old "confirmed" wording
        $this->assertStringContainsString('pending payment', strtolower($html));
        $this->assertStringNotContainsString('Your reservation is confirmed', $html);
    }

    #[Test]
    public function confirmation_email_renders_actual_expiry_deadline_not_hardcoded_minutes(): void
    {
        config(['queue.default' => 'sync']);

        ['buyer' => $buyer, 'ticketType' => $ticketType] = $this->makeReservation();

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $reservationId = $response->json('data.id');
        $reservation   = \App\Models\Reservation::with(['buyer', 'reservationItems'])->find($reservationId);

        $html = (new ReservationConfirmationMail($reservation))->render();

        // The actual expiry time (e.g. "14:35") must appear in the email
        $expectedTime = $reservation->expires_at->format('H:i');
        $this->assertStringContainsString($expectedTime, $html);

        // The old hardcoded "15 minutes" string must not appear
        $this->assertStringNotContainsString('15 minutes', $html);
    }

    #[Test]
    public function confirmation_email_renders_currency_from_config(): void
    {
        config(['queue.default' => 'sync']);
        config(['app.currency' => 'USD']); // override to confirm it is configurable

        ['buyer' => $buyer, 'ticketType' => $ticketType] = $this->makeReservation(
            User::factory()->buyer()->create()
        );

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $reservationId = $response->json('data.id');
        $reservation   = \App\Models\Reservation::with(['buyer', 'reservationItems'])->find($reservationId);

        $html = (new ReservationConfirmationMail($reservation))->render();

        $this->assertStringContainsString('USD', $html);
        $this->assertStringNotContainsString('EGP', $html);
    }

    #[Test]
    public function confirmation_email_defaults_to_egp_when_currency_not_configured(): void
    {
        config(['queue.default' => 'sync']);

        // Directly render the mailable with currency unset to verify the EGP default.
        // We skip the HTTP flow to avoid config leakage from the previous test.
        $buyer  = User::factory()->buyer()->create();
        $event  = $this->publishedEvent();
        $ticketType = $this->ticketType($event);
        $reservation = \App\Models\Reservation::factory()
            ->create(['buyer_id' => $buyer->id, 'total_amount' => '100.00']);
        $reservation->reservationItems()->create([
            'ticket_type_id' => $ticketType->id,
            'quantity'        => 1,
            'unit_price'      => '100.00',
        ]);
        $reservation->load(['buyer', 'reservationItems']);

        // Clear the currency key entirely from the config repository
        $config = app('config');
        $appConfig = $config->get('app');
        unset($appConfig['currency']);
        $config->set('app', $appConfig);

        $html = (new ReservationConfirmationMail($reservation))->render();

        $this->assertStringContainsString('EGP', $html);
    }
}

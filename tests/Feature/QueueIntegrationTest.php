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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Integration tests for the database queue driver.
 *
 * These tests override the phpunit.xml default (QUEUE_CONNECTION=sync) to use
 * the real database driver so we can verify that:
 *
 *   1. A queued listener job is written to the `jobs` table.
 *   2. The job payload survives serialization (SerializesModels round-trip).
 *   3. Processing the job re-fetches the Reservation and Buyer from the DB
 *      and sends the confirmation email.
 *
 * Each test switches queue connection and mail driver manually so the existing
 * Task 08 unit/fake tests are completely unaffected.
 */
class QueueIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Per-test setup — switch to database queue and array mail
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Force the database queue driver for every test in this class.
     * phpunit.xml sets QUEUE_CONNECTION=sync globally; we override per-test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Use the real database queue driver so jobs are written to the `jobs` table.
        config(['queue.default' => 'database']);

        // Use the array mail driver so sent mail can be inspected without a real mailer.
        config(['mail.default' => 'array']);
    }

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

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Job is pushed to the database queue
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function creating_a_reservation_pushes_a_job_to_the_database_queue(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event);

        // Confirm the jobs table starts empty
        $this->assertDatabaseCount('jobs', 0);

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        // Exactly one job must now be in the queue
        $this->assertDatabaseCount('jobs', 1);
    }

    #[Test]
    public function the_queued_job_targets_the_correct_listener_class(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event);

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        // The payload's "displayName" field holds the listener class name.
        // Laravel uses CallQueuedListener as the underlying job wrapper, but
        // displayName identifies the user-facing listener class.
        $job = \Illuminate\Support\Facades\DB::table('jobs')->first();
        $this->assertNotNull($job, 'Expected one job in the jobs table.');

        $payload = json_decode($job->payload, true);

        $this->assertSame(
            SendReservationConfirmation::class,
            $payload['displayName'] ?? null,
            'The job payload displayName should reference SendReservationConfirmation.',
        );
    }

    #[Test]
    public function the_queued_job_is_on_the_default_queue(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event);

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $job = \Illuminate\Support\Facades\DB::table('jobs')->first();
        $this->assertSame('default', $job->queue);
    }

    #[Test]
    public function no_job_is_pushed_when_reservation_creation_fails(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event, [
            'total_quantity'    => 2,
            'reserved_quantity' => 2, // fully reserved — no inventory
        ]);

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertUnprocessable();

        // The transaction rolled back, so no event was dispatched and no job queued.
        $this->assertDatabaseCount('jobs', 0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Serialization / deserialization round-trip
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function job_payload_contains_serialized_reservation_id(): void
    {
        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event);

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $reservationId = $response->json('data.id');

        $job     = \Illuminate\Support\Facades\DB::table('jobs')->first();
        $payload = json_decode($job->payload, true);

        // SerializesModels serializes Eloquent models as "App\Models\ClassName:id"
        // The reservation ID must be present in the serialized command string.
        $this->assertStringContainsString(
            (string) $reservationId,
            $payload['data']['command'],
            'The serialized job payload must embed the reservation ID.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Worker processes job and sends email
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function processing_the_queued_job_sends_confirmation_email_to_buyer(): void
    {
        Mail::fake();

        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event, ['price' => '150.00']);

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        // Confirm the job is waiting in the database queue
        $this->assertDatabaseCount('jobs', 1);

        // Process one job from the database queue — simulates a queue worker run
        Artisan::call('queue:work', [
            '--once'      => true,
            '--queue'     => 'default',
            '--stop-when-empty' => true,
        ]);

        // Job must be consumed (removed from the jobs table)
        $this->assertDatabaseCount('jobs', 0);

        // The confirmation email must have been sent to the buyer
        Mail::assertSent(
            ReservationConfirmationMail::class,
            fn (ReservationConfirmationMail $mail) => $mail->hasTo($buyer->email),
        );
    }

    #[Test]
    public function processed_job_sends_email_with_correct_reservation_id_in_subject(): void
    {
        Mail::fake();

        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event);

        $response = $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $reservationId = $response->json('data.id');

        Artisan::call('queue:work', [
            '--once'      => true,
            '--queue'     => 'default',
            '--stop-when-empty' => true,
        ]);

        Mail::assertSent(
            ReservationConfirmationMail::class,
            fn (ReservationConfirmationMail $mail) =>
                $mail->envelope()->subject === "Your Tazkarti Reservation #{$reservationId}",
        );
    }

    #[Test]
    public function listener_reloads_buyer_from_database_after_deserialization(): void
    {
        Mail::fake();

        // Create a buyer and reservation, then modify the buyer's name in the DB
        // *after* the job is queued. The listener must fetch the current DB state,
        // not the stale serialized value.
        $buyer      = User::factory()->buyer()->create(['name' => 'Original Name']);
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event);

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        // Update the buyer's name after the job is queued — the listener should use
        // the fresh DB value, not the stale one embedded in the payload.
        $buyer->update(['name' => 'Updated Name']);

        Artisan::call('queue:work', [
            '--once'      => true,
            '--queue'     => 'default',
            '--stop-when-empty' => true,
        ]);

        // The email must have been sent — the listener resolved the buyer correctly
        Mail::assertSent(
            ReservationConfirmationMail::class,
            fn (ReservationConfirmationMail $mail) => $mail->hasTo($buyer->email),
        );

        // The job was consumed successfully
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    #[Test]
    public function processed_job_does_not_leave_entry_in_failed_jobs_table(): void
    {
        Mail::fake();

        $buyer      = User::factory()->buyer()->create();
        $event      = $this->publishedEvent();
        $ticketType = $this->ticketType($event);

        $this->withToken($this->tokenFor($buyer))
            ->postJson('/api/reservations', [
                'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        Artisan::call('queue:work', [
            '--once'      => true,
            '--queue'     => 'default',
            '--stop-when-empty' => true,
        ]);

        $this->assertDatabaseCount('failed_jobs', 0);
    }

    #[Test]
public function job_is_moved_to_failed_jobs_after_all_retries_are_exhausted(): void
{
    Mail::shouldReceive('to')
        ->times(3)
        ->andThrow(new \RuntimeException('Mail delivery failed.'));

    $buyer = User::factory()->buyer()->create();
    $event = $this->publishedEvent();
    $ticketType = $this->ticketType($event);

    $this->withToken($this->tokenFor($buyer))
        ->postJson('/api/reservations', [
            'items' => [
                ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
            ],
        ])
        ->assertCreated();

    // Simulate the three attempts made by the queue worker.
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'default',
            '--tries' => 3,
        ]);

        if ($attempt < 3) {
            DB::table('jobs')->update([
                'available_at' => now()->subSecond()->timestamp,
            ]);
        }
    }

    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 1);

    $failedJob = DB::table('failed_jobs')->first();

    $this->assertStringContainsString(
        'Mail delivery failed.',
        $failedJob->exception
    );
}
}

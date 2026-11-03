<?php

namespace Database\Seeders;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Enums\ReservationStatusEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BulkEventSeeder extends Seeder
{
    // ── Configuration constants ───────────────────────────────────────────────

    /** Stable identifier for the bulk test event. */
    private const BULK_EVENT_TITLE = 'Grand Tazkarti Showcase 2027';

    /** Email of the organizer who owns the bulk event. */
    private const ORGANIZER_EMAIL = 'organizer1@tazkarti.test';

    /** Name of the venue for the bulk event. */
    private const VENUE_NAME = 'Cairo Stadium';

    /** Target number of distinct ticket holders. */
    private const TARGET_TICKET_HOLDERS = 1000;

    /** Batch size for INSERT operations — keeps memory low. */
    private const BATCH_SIZE = 100;

    // ─────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        // ── 1. Ensure the bulk event exists ──────────────────────────────────
        $event = $this->ensureEvent();

        // ── 2. Ensure the ticket type exists ─────────────────────────────────
        $ticketType = $this->ensureTicketType($event);

        // ── 3. Determine how many ticket holders already exist ───────────────
        $existing = $this->countExistingHolders($ticketType);

        $deficit = max(0, self::TARGET_TICKET_HOLDERS - $existing);

        if ($deficit === 0) {
            $this->command->info(
                'BulkEventSeeder: target already met — ' .
                self::TARGET_TICKET_HOLDERS . ' ticket holders already exist.'
            );
            $this->syncInventory($ticketType);
            return;
        }

        $this->command->info(
            "BulkEventSeeder: {$existing} holders already exist — creating {$deficit} more."
        );

        // ── 4. Create buyers, reservations, items, and tickets in batches ────
        $this->createHolders($ticketType, $deficit);

        // ── 5. Sync inventory counters to match actual records ───────────────
        $this->syncInventory($ticketType);

        $total = $this->countExistingHolders($ticketType);
        $this->command->info(
            "BulkEventSeeder: done — {$total} total ticket holders for '{$event->title}'."
        );
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Ensure the bulk event exists. Returns the Event model.
     */
    private function ensureEvent(): Event
    {
        $organizer = User::where('email', self::ORGANIZER_EMAIL)->firstOrFail();
        $venue     = Venue::where('name', self::VENUE_NAME)->firstOrFail();

        $event = Event::firstOrCreate(
            ['title' => self::BULK_EVENT_TITLE],
            [
                'organizer_id' => $organizer->id,
                'venue_id'     => $venue->id,
                'description'  => 'The flagship Tazkarti event used for bulk notification testing with 1,000+ ticket holders.',
                'event_type'   => EventTypeEnum::Concert->value,
                'status'       => EventStatusEnum::Published->value,
                'starts_at'    => '2027-12-31 20:00:00',
                'ends_at'      => '2028-01-01 02:00:00',
                'published_at' => now(),
            ],
        );

        if ($event->wasRecentlyCreated) {
            $this->command->info("BulkEventSeeder: created event '{$event->title}'.");
        }

        return $event;
    }

    /**
     * Ensure a single ticket type exists for the bulk event.
     */
    private function ensureTicketType(Event $event): TicketType
    {
        return TicketType::firstOrCreate(
            [
                'event_id' => $event->id,
                'name'     => 'General Admission',
            ],
            [
                // Will be updated by syncInventory() after all records are created
                'total_quantity'    => max(self::TARGET_TICKET_HOLDERS, 1200),
                'reserved_quantity' => 0,
                'sold_quantity'     => 0,
                'price'             => 200.00,
                'max_per_order'     => 1,
                'description'       => 'Standard ticket for the Grand Tazkarti Showcase.',
            ],
        );
    }

    /**
     * Count how many paid reservations + valid tickets already exist for this ticket type.
     * A "holder" is a buyer with a paid reservation whose item references this ticket type
     * AND who has at least one valid ticket of this type.
     */
    private function countExistingHolders(TicketType $ticketType): int
{
    return DB::table('tickets')
        ->join('reservations', 'tickets.reservation_id', '=', 'reservations.id')
        ->join('reservation_items', function ($join) use ($ticketType) {
            $join->on('reservation_items.reservation_id', '=', 'reservations.id')
                ->where('reservation_items.ticket_type_id', '=', $ticketType->id);
        })
        ->where('tickets.ticket_type_id', $ticketType->id)
        ->where('tickets.status', TicketStatusEnum::Valid->value)
        ->where('reservations.status', ReservationStatusEnum::Paid->value)
        ->distinct()
        ->count('reservations.buyer_id');
}

    /**
     * Create $count new buyer → reservation → reservation_item → ticket chains.
     * Processed in batches of BATCH_SIZE to keep memory usage flat.
     */
   

private function createHolders(TicketType $ticketType, int $count): void
{
    $price = (float) $ticketType->price;
    $paidAt = now()->subDays(7);
    $password = Hash::make('password');
    $remaining = $count;

    while ($remaining > 0) {
        $batchSize = min($remaining, self::BATCH_SIZE);

        DB::transaction(function () use ($ticketType, $price, $paidAt, $password, $batchSize) {
            // a) Create buyers
            $buyerRows = [];
            $now = now();

            for ($i = 0; $i < $batchSize; $i++) {
                $buyerRows[] = [
                    'name'              => fake()->name(),
                    'email'             => fake()->unique()->safeEmail(),
                    'password'          => $password,
                    'role'              => UserRoleEnum::Buyer->value,
                    'email_verified_at' => $now,
                    'remember_token'    => Str::random(10),
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            DB::table('users')->insert($buyerRows);

            // Retrieve the exact buyer IDs using their unique emails
            $buyerEmails = array_column($buyerRows, 'email');

            $buyers = DB::table('users')
                ->whereIn('email', $buyerEmails)
                ->pluck('id', 'email');

            $buyerIds = [];

            foreach ($buyerRows as $buyerRow) {
                $buyerIds[] = $buyers[$buyerRow['email']];
            }

            // b) Create paid reservations
            $reservations = [];

            foreach ($buyerIds as $buyerId) {
                $reservationId = DB::table('reservations')->insertGetId([
                    'buyer_id'     => $buyerId,
                    'status'       => ReservationStatusEnum::Paid->value,
                    'total_amount' => $ticketType->price,
                    'expires_at'   => null,
                    'paid_at'      => $paidAt,
                    'refunded_at'  => null,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);

                $reservations[$buyerId] = $reservationId;
            }

            // c) Create reservation items
            $itemRows = [];

            foreach ($buyerIds as $buyerId) {
                $itemRows[] = [
                    'reservation_id' => $reservations[$buyerId],
                    'ticket_type_id' => $ticketType->id,
                    'quantity'       => 1,
                    'unit_price'     => $price,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }

            DB::table('reservation_items')->insert($itemRows);

            // d) Create tickets
            $ticketRows = [];

            foreach ($buyerIds as $buyerId) {
                $ticketRows[] = [
                    'reservation_id' => $reservations[$buyerId],
                    'ticket_type_id' => $ticketType->id,
                    'code'           => $this->generateUniqueCode(),
                    'status'         => TicketStatusEnum::Valid->value,
                    'file_path'      => null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }

            DB::table('tickets')->insert($ticketRows);
        });

        $remaining -= $batchSize;

        $done = $count - $remaining;
        $this->command->info("BulkEventSeeder: {$done}/{$count} holders created...");
    }
}

    /**
     * Sync inventory counters on the ticket type to match actual DB records.
     * sold_quantity = count of valid tickets for this type with a paid reservation.
     * reserved_quantity is set to 0 after the bulk seed (no pending reservations).
     * total_quantity is updated if sold exceeds the originally set total.
     */
    private function syncInventory(TicketType $ticketType): void
{
    $soldCount = DB::table('reservation_items')
        ->join('reservations', 'reservation_items.reservation_id', '=', 'reservations.id')
        ->where('reservation_items.ticket_type_id', $ticketType->id)
        ->where('reservations.status', ReservationStatusEnum::Paid->value)
        ->sum('reservation_items.quantity');

    $reservedCount = DB::table('reservation_items')
        ->join('reservations', 'reservation_items.reservation_id', '=', 'reservations.id')
        ->where('reservation_items.ticket_type_id', $ticketType->id)
        ->where('reservations.status', ReservationStatusEnum::Pending->value)
        ->where('reservations.expires_at', '>', now())
        ->sum('reservation_items.quantity');

    $newTotal = max(
        (int) $ticketType->total_quantity,
        $soldCount + $reservedCount
    );

    $ticketType->update([
        'sold_quantity'     => $soldCount,
        'reserved_quantity' => $reservedCount,
        'total_quantity'    => $newTotal,
    ]);

    $this->command->info(
        "BulkEventSeeder: inventory synced — " .
        "sold={$soldCount}, reserved={$reservedCount}, total={$newTotal}."
    );
}

    /**
     * Generate a unique ticket code in XXXX-XXXX-XXXX format.
     * Retries on the extremely unlikely collision.
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(4)) . '-' .
                    strtoupper(Str::random(4)) . '-' .
                    strtoupper(Str::random(4));
        } while (DB::table('tickets')->where('code', $code)->exists());

        return $code;
    }
}

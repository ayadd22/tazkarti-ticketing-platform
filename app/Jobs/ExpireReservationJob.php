<?php

namespace App\Jobs;

use App\Enums\ReservationStatusEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Services\EventCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ExpireReservationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 60;

    public function __construct(
        public int $reservationId
    ) {}

    public function handle(EventCacheService $cache): void
    {
        $wasExpired = DB::transaction(function () {
            $reservation = Reservation::query()
                ->whereKey($this->reservationId)
                ->lockForUpdate()
                ->first();

            if (
                ! $reservation ||
                $reservation->status !== ReservationStatusEnum::Pending ||
                ! $reservation->expires_at ||
                $reservation->expires_at->isFuture()
            ) {
                return false;
            }

            $items = $reservation->reservationItems()
                ->orderBy('ticket_type_id')
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $ticketType = TicketType::query()
                    ->whereKey($item->ticket_type_id)
                    ->lockForUpdate()
                    ->first();

                if (! $ticketType) {
                    continue;
                }

                if ($ticketType->reserved_quantity < $item->quantity) {
                    throw new RuntimeException(
                        "Insufficient reserved quantity for ticket type {$ticketType->id}."
                    );
                }

                $ticketType->decrement(
                    'reserved_quantity',
                    $item->quantity
                );
            }

            $reservation->tickets()
                ->where('status', TicketStatusEnum::Pending->value)
                ->update([
                    'status' => TicketStatusEnum::Cancelled->value,
                ]);

            $reservation->update([
                'status' => ReservationStatusEnum::Expired->value,
            ]);

            return true;
        });

        if ($wasExpired) {
            $cache->invalidate();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Expire reservation job failed', [
            'reservation_id' => $this->reservationId,
            'exception' => $exception->getMessage(),
        ]);
    }
}

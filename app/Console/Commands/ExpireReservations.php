<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatusEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Reservation;
use App\Models\TicketType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Expire pending reservations and release reserved ticket inventory';

    public function handle(): int
    {
        $expiredCount = 0;

        Reservation::query()
            ->pending()
            ->expiredAt()
            ->select('id')
            ->chunkById(100, function ($reservations) use (&$expiredCount) {
                foreach ($reservations as $reservation) {
                    $wasExpired = DB::transaction(function () use ($reservation) {
                        $lockedReservation = Reservation::query()
                            ->whereKey($reservation->id)
                            ->lockForUpdate()
                            ->first();

                        if (
                            ! $lockedReservation ||
                            $lockedReservation->status !== ReservationStatusEnum::Pending ||
                            $lockedReservation->expires_at->isFuture()
                        ) {
                            return false;
                        }

                        $items = $lockedReservation->reservationItems()
                            ->lockForUpdate()
                            ->get();

                        foreach ($items as $item) {
                            $ticketType = TicketType::query()
                                ->whereKey($item->ticket_type_id)
                                ->lockForUpdate()
                                ->first();

                            if ($ticketType) {
                              if ($ticketType->reserved_quantity < $item->quantity) {
                                throw new \RuntimeException(
                                      "Insufficient reserved quantity for ticket type {$ticketType->id}."
                                     );
                                }

                                 $ticketType->decrement(
                                    'reserved_quantity',
                                    $item->quantity
                                   );
                               }
                        }

                        $lockedReservation->tickets()
                            ->where('status', TicketStatusEnum::Pending->value)
                            ->update([
                                'status' => TicketStatusEnum::Cancelled->value,
                            ]);

                        $lockedReservation->update([
                            'status' => ReservationStatusEnum::Expired->value,
                        ]);

                        return true;
                    });

                    if ($wasExpired) {
                        $expiredCount++;
                    }
                }
            });

        $this->info("Expired {$expiredCount} reservation(s).");

        return self::SUCCESS;
    }
}

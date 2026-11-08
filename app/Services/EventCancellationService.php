<?php

namespace App\Services;

use App\Enums\EventStatusEnum;
use App\Enums\ReservationStatusEnum;
use App\Enums\TicketStatusEnum;
use App\Jobs\SendEventCancellationNotificationJob;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventCancellationService
{
    public function cancel(Event $event): Event
    {
        return DB::transaction(function () use ($event) {
            $event = Event::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($event->status === EventStatusEnum::Cancelled) {
                throw ValidationException::withMessages([
                    'event' => ['This event is already cancelled.'],
                ]);
            }

            $ticketTypes = TicketType::query()
                ->where('event_id', $event->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $ticketTypeIds = $ticketTypes->pluck('id');

            $reservations = Reservation::query()
                ->whereHas('reservationItems', function ($query) use ($ticketTypeIds) {
                    $query->whereIn('ticket_type_id', $ticketTypeIds);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $items = ReservationItem::query()
                    ->where('reservation_id', $reservation->id)
                    ->lockForUpdate()
                    ->get();

                // A reservation must belong to this event only.
                if ($items->contains(fn ($item) => ! $ticketTypeIds->contains($item->ticket_type_id))) {
                    throw ValidationException::withMessages([
                        'reservation' => [
                            "Reservation {$reservation->id} contains tickets from another event.",
                        ],
                    ]);
                }

                if ($reservation->status === ReservationStatusEnum::Pending) {
                    foreach ($items as $item) {
                        TicketType::whereKey($item->ticket_type_id)
                            ->decrement('reserved_quantity', $item->quantity);
                    }

                    $reservation->update([
                        'status' => ReservationStatusEnum::Expired,
                    ]);
                } elseif ($reservation->status === ReservationStatusEnum::Paid) {
                    foreach ($items as $item) {
                        TicketType::whereKey($item->ticket_type_id)
                            ->decrement('sold_quantity', $item->quantity);
                    }

                    $reservation->update([
                        'status' => ReservationStatusEnum::Refunded,
                        'refunded_at' => now(),
                    ]);
                } else {
                    continue;
                }

                Ticket::query()
                    ->where('reservation_id', $reservation->id)
                    ->whereIn('ticket_type_id', $ticketTypeIds)
                    ->update([
                        'status' => TicketStatusEnum::Cancelled,
                    ]);

                DB::afterCommit(
                    fn () => SendEventCancellationNotificationJob::dispatch(
                        $reservation->id,
                        $event->id
                    )
                );
            }

            $event->update([
                'status' => EventStatusEnum::Cancelled,
            ]);

            return $event->fresh();
        });
    }
}

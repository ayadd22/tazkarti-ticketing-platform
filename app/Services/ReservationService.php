<?php

namespace App\Services;

use App\Enums\ReservationStatusEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\EventCacheService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Jobs\ExpireReservationJob;
use App\Models\Event;
use App\Enums\EventStatusEnum;


class ReservationService
{
    
    private const EXPIRY_MINUTES = 10;

  
    private const CODE_MAX_ATTEMPTS = 5;


    
public function create(User $buyer, array $items): Reservation
{
    $merged = $this->mergeItems($items);

    $reservation = DB::transaction(function () use ($buyer, $merged) {
        $ticketTypeIds = array_keys($merged);

        $ticketTypes = TicketType::whereIn('id', $ticketTypeIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $errors = [];

        foreach ($merged as $ticketTypeId => $quantity) {
            $ticketType = $ticketTypes->get($ticketTypeId);

            if ($ticketType === null) {
                $errors["items.{$ticketTypeId}"] = [
                    "Ticket type #{$ticketTypeId} no longer exists."
                ];
                continue;
            }

            if ($quantity > $ticketType->max_per_order) {
                $errors["items"] = array_merge(
                    $errors["items"] ?? [],
                    ["You may order at most {$ticketType->max_per_order} ticket(s) for \"{$ticketType->name}\"."]
                );
            }

            $available = $ticketType->total_quantity
                - $ticketType->sold_quantity
                - $ticketType->reserved_quantity;

            if ($quantity > $available) {
                $errors["items"] = array_merge(
                    $errors["items"] ?? [],
                    ["Not enough inventory for \"{$ticketType->name}\". Requested {$quantity}, available {$available}."]
                );
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        
        $eventIds = $ticketTypes->pluck('event_id')->unique();

        if ($eventIds->count() !== 1) {
            throw ValidationException::withMessages([
                'items' => ['A reservation can contain tickets from one event only.'],
            ]);
        }

     
        $event = Event::findOrFail($eventIds->first());

        if ($event->status !== EventStatusEnum::Published) {
            throw ValidationException::withMessages([
                'items' => ['Tickets can only be reserved for published events.'],
            ]);
        }

        $totalAmount = 0;

        foreach ($merged as $ticketTypeId => $quantity) {
            $totalAmount += (float) $ticketTypes->get($ticketTypeId)->price * $quantity;
        }

        $reservation = Reservation::create([
            'buyer_id' => $buyer->id,
            'status' => ReservationStatusEnum::Pending->value,
            'total_amount' => round($totalAmount, 2),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        foreach ($merged as $ticketTypeId => $quantity) {
            $ticketType = $ticketTypes->get($ticketTypeId);

            $reservation->reservationItems()->create([
                'ticket_type_id' => $ticketTypeId,
                'quantity' => $quantity,
                'unit_price' => $ticketType->price,
            ]);

            for ($i = 0; $i < $quantity; $i++) {
                $reservation->tickets()->create([
                    'ticket_type_id' => $ticketTypeId,
                    'code' => $this->generateUniqueCode(),
                    'status' => TicketStatusEnum::Pending->value,
                ]);
            }

            $ticketType->increment('reserved_quantity', $quantity);
        }

        return $reservation->load(['reservationItems', 'tickets']);
    });

    app(EventCacheService::class)->invalidate();

    ExpireReservationJob::dispatch($reservation->id)
        ->delay($reservation->expires_at)
        ->afterCommit();

    return $reservation;
}



    public function confirmPayment(Reservation $reservation): Reservation
  {
    return DB::transaction(function () use ($reservation) {

        $reservation = Reservation::query()
            ->whereKey($reservation->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($reservation->status !== ReservationStatusEnum::Pending) {
            throw new HttpException(409, 'Reservation is not pending.');
        }

        if ($reservation->expires_at->isPast()) {
            throw new HttpException(409, 'Reservation has expired.');
        }

        $items = $reservation->reservationItems()
            ->get();

        $ticketTypeIds = $items->pluck('ticket_type_id')
            ->unique()
            ->sort()
            ->values();

        $ticketTypes = TicketType::query()
            ->whereIn('id', $ticketTypeIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

            foreach ($ticketTypes as $ticketType) {
           $ticketType->load('event');

             if ($ticketType->event->status !== EventStatusEnum::Published) {
            throw ValidationException::withMessages([
            'reservation' => ['Payment cannot be confirmed because the event is no longer published.'],
          ]);
           }
       }

        foreach ($items as $item) {
            $ticketType = $ticketTypes->get($item->ticket_type_id);
              if (!$ticketType || $ticketType->reserved_quantity < $item->quantity ||
                ($ticketType->sold_quantity + $item->quantity) > $ticketType->total_quantity) {
                throw new \RuntimeException('Reserved inventory is inconsistent.');
            }

            $ticketType->decrement('reserved_quantity', $item->quantity);
            $ticketType->increment('sold_quantity', $item->quantity);
        }

        $reservation->tickets()
            ->where('status', TicketStatusEnum::Pending->value)
            ->update([
                'status' => TicketStatusEnum::Valid->value,
            ]);

        $reservation->update([
            'status' => ReservationStatusEnum::Paid->value,
            'paid_at' => now(),
        ]);

        return $reservation->load([
            'reservationItems',
            'tickets',
        ]);
    });
  }
   
    private function mergeItems(array $items): array
    {
        $merged = [];

        foreach ($items as $item) {
            $id = (int) $item['ticket_type_id'];
            $merged[$id] = ($merged[$id] ?? 0) + (int) $item['quantity'];
        }

        return $merged;
    }

    
    private function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < self::CODE_MAX_ATTEMPTS; $attempt++) {
            $code = strtoupper(Str::random(4)) . '-'
                  . strtoupper(Str::random(4)) . '-'
                  . strtoupper(Str::random(4));

            if (!DB::table('tickets')->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate a unique ticket code after ' . self::CODE_MAX_ATTEMPTS . ' attempts.');
    }
}

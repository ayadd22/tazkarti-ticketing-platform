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

class ReservationService
{
    
    private const EXPIRY_MINUTES = 15;

  
    private const CODE_MAX_ATTEMPTS = 5;


    public function create(User $buyer, array $items): Reservation
    {
       
        $merged = $this->mergeItems($items);

        return DB::transaction(function () use ($buyer, $merged) {
            $ticketTypeIds = array_keys($merged);

            $ticketTypes = TicketType::whereIn('id', $ticketTypeIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            
            $errors = [];

            foreach ($merged as $ticketTypeId => $quantity) {
                $ticketType = $ticketTypes->get($ticketTypeId);

                if ($ticketType === null) {
                    $errors["items.{$ticketTypeId}"] = ["Ticket type #{$ticketTypeId} no longer exists."];
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

           
            $totalAmount = 0;

            foreach ($merged as $ticketTypeId => $quantity) {
                $totalAmount += (float) $ticketTypes->get($ticketTypeId)->price * $quantity;
            }


            $reservation = Reservation::create([
                'buyer_id'     => $buyer->id,
                'status'       => ReservationStatusEnum::Pending->value,
                'total_amount' => round($totalAmount, 2),
                'expires_at'   => now()->addMinutes(self::EXPIRY_MINUTES),
            ]);

          
            foreach ($merged as $ticketTypeId => $quantity) {
                $ticketType = $ticketTypes->get($ticketTypeId);

                
                $reservation->reservationItems()->create([
                    'ticket_type_id' => $ticketTypeId,
                    'quantity'       => $quantity,
                    'unit_price'     => $ticketType->price,
                ]);

           
                for ($i = 0; $i < $quantity; $i++) {
                    $reservation->tickets()->create([
                        'ticket_type_id' => $ticketTypeId,
                        'code'           => $this->generateUniqueCode(),
                        'status'         => TicketStatusEnum::Valid->value,
                    ]);
                }

                
                $ticketType->increment('reserved_quantity', $quantity);
            }

            
            return $reservation->load(['reservationItems', 'tickets']);
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

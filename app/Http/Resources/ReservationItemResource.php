<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class ReservationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'ticket_type_id' => $this->ticket_type_id,
            'quantity'       => $this->quantity,
            'unit_price'     => $this->unit_price,
            'subtotal'       => number_format((float) $this->unit_price * $this->quantity, 2, '.', ''),
            'ticket_type'    => TicketTypeResource::make($this->whenLoaded('ticketType')),
        ];
    }
}

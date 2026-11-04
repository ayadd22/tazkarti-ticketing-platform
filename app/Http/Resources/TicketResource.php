<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'ticket_type_id' => $this->ticket_type_id,
            'code'           => $this->code,
            'status'         => $this->status->value,
            'ticket_type'    => TicketTypeResource::make($this->whenLoaded('ticketType')),
        ];
    }
}

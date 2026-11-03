<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'description'  => $this->description,
            'event_type'   => $this->event_type->value,
            'status'       => $this->status->value,
            'starts_at'    => $this->starts_at->toISOString(),
            'ends_at'      => $this->ends_at->toISOString(),
            'published_at' => $this->published_at?->toISOString(),

            
            'venue'        => VenueResource::make($this->whenLoaded('venue')),
            'ticket_types' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
        ];
    }
}

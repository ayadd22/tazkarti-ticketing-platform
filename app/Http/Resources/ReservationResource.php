<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status->value,
            'total_amount' => $this->total_amount,
            'expires_at'   => $this->expires_at?->toISOString(),
            'paid_at'      => $this->paid_at?->toISOString(),
            'refunded_at'  => $this->refunded_at?->toISOString(),
            'created_at'   => $this->created_at?->toISOString(),
            'items'        => ReservationItemResource::collection($this->whenLoaded('reservationItems')),
            'tickets'      => TicketResource::collection($this->whenLoaded('tickets')),
        ];
    }
}

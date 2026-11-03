<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class TicketTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'description'       => $this->description,
            'price'             => $this->price,
            'total_quantity'    => $this->total_quantity,
            'available_quantity' => max(0, $this->total_quantity - $this->sold_quantity - $this->reserved_quantity),
            'max_per_order'     => $this->max_per_order,
        ];
    }
}

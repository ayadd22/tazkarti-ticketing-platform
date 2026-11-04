<?php

namespace App\Http\Requests;

use App\Models\TicketType;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketTypeRequest extends FormRequest
{
    
    public function authorize(): bool
    {
        return $this->user()->can('create', [TicketType::class, $this->route('event')]);
    }

    
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'total_quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'max_per_order' => [
                'required',
                'integer',
                'min:1',
                // max_per_order must not exceed the total available supply
                'lte:total_quantity',
            ],
        ];
    }

   
    public function attributes(): array
    {
        return [
            'total_quantity' => 'total quantity',
            'max_per_order'  => 'max per order',
        ];
    }
}

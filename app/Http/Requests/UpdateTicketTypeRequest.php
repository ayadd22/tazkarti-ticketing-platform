<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;


class UpdateTicketTypeRequest extends FormRequest
{
  
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticketType'));
    }

  
    public function rules(): array
    {
        
        $ticketType           = $this->route('ticketType');
        $effectiveTotalQty    = $this->input('total_quantity') ?? ($ticketType?->total_quantity);

        $maxPerOrderRules = [
            'sometimes',
            'integer',
            'min:1',
        ];

        
        if ($effectiveTotalQty !== null) {
            $maxPerOrderRules[] = 'max:' . (int) $effectiveTotalQty;
        }

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'price' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'total_quantity' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'max_per_order' => $maxPerOrderRules,
        ];
    }

    
    public function attributes(): array
    {
        return [
            'total_quantity' => 'total quantity',
            'max_per_order'  => 'max per order',
        ];
    }

 
    public function messages(): array
    {
        return [
            'max_per_order.max' => 'Max per order may not exceed the total quantity.',
        ];
    }
}

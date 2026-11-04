<?php

namespace App\Http\Requests;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    
    public function authorize(): bool
    {
        return $this->user()->can('create', Reservation::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('items') && is_array($this->input('items'))) {
            $this->merge([
                'items' => array_map(
                    fn (mixed $item) => is_array($item)
                        ? array_intersect_key($item, array_flip(['ticket_type_id', 'quantity']))
                        : $item,
                    $this->input('items')
                ),
            ]);
        }
    }

    
    public function rules(): array
    {
        return [
            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.ticket_type_id' => [
                'required',
                'integer',
                'exists:ticket_types,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    
    public function attributes(): array
    {
        return [
            'items'                  => 'reservation items',
            'items.*.ticket_type_id' => 'ticket type',
            'items.*.quantity'       => 'quantity',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                  => 'At least one reservation item is required.',
            'items.min'                       => 'At least one reservation item is required.',
            'items.*.ticket_type_id.required' => 'Each item must specify a ticket type.',
            'items.*.ticket_type_id.exists'   => 'The selected ticket type does not exist.',
            'items.*.quantity.required'       => 'Each item must specify a quantity.',
            'items.*.quantity.min'            => 'Quantity must be at least 1.',
        ];
    }
}

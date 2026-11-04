<?php

namespace App\Http\Requests;

use App\Enums\EventTypeEnum;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Event::class);
    }

    
    public function rules(): array
    {
        return [
            'venue_id' => [
                'required',
                'integer',
                Rule::exists('venues', 'id'),
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'event_type' => [
                'required',
                'string',
                Rule::in(array_column(EventTypeEnum::cases(), 'value')),
            ],

            'starts_at' => [
                'required',
                'date',
                'after:now',
            ],

            'ends_at' => [
                'required',
                'date',
                'after:starts_at',
            ],
        ];
    }

   
    public function attributes(): array
    {
        return [
            'venue_id'   => 'venue',
            'event_type' => 'event type',
            'starts_at'  => 'start date',
            'ends_at'    => 'end date',
        ];
    }
}

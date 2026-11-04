<?php

namespace App\Http\Requests;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
  
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }


    public function rules(): array
    {
        $event = $this->route('event');

       
        $effectiveStartsAt = $this->input('starts_at') ?? $event?->starts_at?->toDateTimeString();
        $effectiveEndsAt   = $this->input('ends_at')   ?? $event?->ends_at?->toDateTimeString();

        return [
            'venue_id' => [
                'sometimes',
                'integer',
                Rule::exists('venues', 'id'),
            ],

            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'event_type' => [
                'sometimes',
                'string',
                Rule::in(array_column(EventTypeEnum::cases(), 'value')),
            ],

            'status' => [
                'sometimes',
                'string',
                Rule::in(array_column(EventStatusEnum::cases(), 'value')),
            ],

           
            'starts_at' => array_filter([
                'sometimes',
                'date',
                'after:now',
                $effectiveEndsAt ? 'before:' . $effectiveEndsAt : null,
            ], fn ($rule) => $rule !== null),

            
            'ends_at' => array_filter([
                'sometimes',
                'date',
                $effectiveStartsAt ? 'after:' . $effectiveStartsAt : 'after:now',
            ], fn ($rule) => $rule !== null),
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

   
    public function messages(): array
    {
        return [
            'starts_at.before' => 'The start date must be before the end date.',
            'ends_at.after'    => 'The end date must be after the start date.',
        ];
    }
}

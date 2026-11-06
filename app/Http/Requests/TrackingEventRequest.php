<?php

namespace App\Http\Requests;

use App\Enums\TrackingEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('event_type'))) {
            $this->merge(['event_type' => strtoupper($this->input('event_type'))]);
        }
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::in(TrackingEventType::manualValues())],
            'location_id' => ['required_unless:event_type,BOOKED', 'nullable', 'exists:locations,id'],
            'occurred_at' => ['nullable', 'date'],
            'estimated_arrival_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}

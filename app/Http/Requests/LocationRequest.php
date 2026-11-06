<?php

namespace App\Http\Requests;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // access is enforced by the role middleware on the route
    }

    protected function prepareForValidation(): void
    {
        foreach (['code', 'country_code', 'region'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => strtoupper(trim($this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'code' => [$required, 'string', 'alpha_num', 'min:3', 'max:10',
                Rule::unique('locations', 'code')->ignore($this->route('location'))],
            'type' => ['sometimes', Rule::in(Location::TYPES)],
            'city' => [$required, 'string', 'max:255'],
            'country' => [$required, 'string', 'max:255'],
            'country_code' => [$required, 'string', 'size:2', 'alpha'],
            'region' => ['nullable', Rule::in(Location::REGIONS)],
            'latitude' => [$required, 'numeric', 'between:-90,90'],
            'longitude' => [$required, 'numeric', 'between:-180,180'],
        ];
    }
}

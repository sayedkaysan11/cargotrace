<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role middleware + ShipmentPolicy enforce access
    }

    public function rules(): array
    {
        $store = $this->isMethod('POST');
        $req = $store ? 'required' : 'sometimes';

        return [
            // Fixed once created: sending them on update is rejected ("prohibited").
            'customer_id' => $store
                ? ['required', Rule::exists('users', 'id')->where('role', UserRole::Customer->value)->where('is_active', true)]
                : ['prohibited'],
            'origin_location_id' => $store ? ['required', 'exists:locations,id'] : ['prohibited'],
            'destination_location_id' => $store ? ['required', 'exists:locations,id', 'different:origin_location_id'] : ['prohibited'],
            'scenario' => $store ? ['sometimes', Rule::in(array_keys(config('cargotrace.groups')))] : ['prohibited'],
            'items' => $store ? ['required', 'array', 'min:1', 'max:50'] : ['prohibited'],

            'carrier_id' => [$req, Rule::exists('carriers', 'id')->where('is_active', true)],
            'cargo_type' => [$req, 'string', 'max:60'],
            'expected_departure_at' => [$req, 'date'],
            'expected_arrival_at' => [$req, 'date', 'after:expected_departure_at'],

            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.hs_code' => ['nullable', 'string', 'max:12'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.weight_kg' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.volume_cbm' => ['required_with:items', 'numeric', 'gt:0'],
        ];
    }
}

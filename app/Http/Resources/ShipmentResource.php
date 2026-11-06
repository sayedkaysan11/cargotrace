<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_number' => $this->shipment_number,
            'scenario' => $this->scenario,
            'status' => $this->current_status->value,
            'cargo_type' => $this->cargo_type,
            'total_weight_kg' => (float) $this->total_weight_kg,
            'total_volume_cbm' => (float) $this->total_volume_cbm,

            'expected_departure_at' => $this->expected_departure_at,
            'expected_arrival_at' => $this->expected_arrival_at,
            'estimated_arrival_at' => $this->estimated_arrival_at,
            'actual_departure_at' => $this->actual_departure_at,
            'actual_arrival_at' => $this->actual_arrival_at,

            'delay' => [
                'is_delayed' => $this->is_delayed,
                'minutes' => $this->delay_minutes,
                'hours' => round($this->delay_minutes / 60, 1),
                'severity' => $this->delay_severity?->value,
                'threshold_hours' => $this->delayThresholdHours(),
            ],

            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'company_name' => $this->customer->company_name,
            ]),
            'carrier' => new CarrierResource($this->whenLoaded('carrier')),
            'origin' => new LocationResource($this->whenLoaded('origin')),
            'destination' => new LocationResource($this->whenLoaded('destination')),

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'description' => $i->description,
                'hs_code' => $i->hs_code,
                'quantity' => $i->quantity,
                'weight_kg' => (float) $i->weight_kg,
                'volume_cbm' => (float) $i->volume_cbm,
            ])),

            'current_location' => $this->whenLoaded('latestLocatedEvent', fn () => $this->latestLocatedEvent
                ? [
                    'latitude' => $this->latestLocatedEvent->latitude,
                    'longitude' => $this->latestLocatedEvent->longitude,
                    'as_of' => $this->latestLocatedEvent->occurred_at,
                ]
                : null),

            'created_at' => $this->created_at,
        ];
    }
}

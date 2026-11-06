<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type->value,
            'status' => $this->status?->value,
            'is_exception' => $this->is_exception,
            'occurred_at' => $this->occurred_at,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location' => new LocationResource($this->whenLoaded('location')),
        ];
    }
}

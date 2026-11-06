<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'city' => $this->city,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'region' => $this->region,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}

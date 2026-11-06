<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesCargoData
{
    protected function makeUser(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'is_active' => true], $attributes));
    }

    protected function makeCarrier(array $attributes = []): Carrier
    {
        return Carrier::create(array_merge(['name' => 'Test Carrier', 'code' => Str::upper(Str::random(6))], $attributes));
    }

    protected function makeLocation(string $city = 'Testville', array $attributes = []): Location
    {
        return Location::create(array_merge([
            'name' => "$city Port",
            'code' => Str::upper(Str::random(5)),
            'city' => $city,
            'country' => 'Testland',
            'country_code' => 'TL',
            'latitude' => 1.0,
            'longitude' => 100.0,
        ], $attributes));
    }

    /** $overrides may set any shipments column, e.g. current_status, scenario, expected_* dates. */
    protected function makeShipment(User $customer, array $overrides = []): Shipment
    {
        $attributes = array_merge([
            'shipment_number' => 'CT-TEST-' . Str::upper(Str::random(8)),
            'customer_id' => $customer->id,
            'carrier_id' => $this->makeCarrier()->id,
            'origin_location_id' => $this->makeLocation('Origin')->id,
            'destination_location_id' => $this->makeLocation('Destination')->id,
            'cargo_type' => 'Test cargo',
            'expected_departure_at' => now()->addDay(),
            'expected_arrival_at' => now()->addDays(20),
        ], $overrides);

        $attributes['estimated_arrival_at'] ??= $attributes['expected_arrival_at'];

        return Shipment::create($attributes);
    }
}

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

    protected function makeShipment(User $customer): Shipment
    {
        $carrier = Carrier::create(['name' => 'Test Carrier', 'code' => Str::upper(Str::random(6))]);

        $location = fn (string $city) => Location::create([
            'name' => "$city Port",
            'code' => Str::upper(Str::random(5)),
            'city' => $city,
            'country' => 'Testland',
            'country_code' => 'TL',
            'latitude' => 1.0,
            'longitude' => 100.0,
        ]);

        return Shipment::create([
            'shipment_number' => 'CT-TEST-' . Str::upper(Str::random(8)),
            'customer_id' => $customer->id,
            'carrier_id' => $carrier->id,
            'origin_location_id' => $location('Origin')->id,
            'destination_location_id' => $location('Destination')->id,
            'cargo_type' => 'Test cargo',
            'expected_departure_at' => now()->addDay(),
            'expected_arrival_at' => now()->addDays(20),
        ]);
    }
}

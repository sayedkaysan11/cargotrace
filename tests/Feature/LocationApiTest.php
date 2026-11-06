<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCargoData;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase, CreatesCargoData;

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Port of Test', 'code' => 'tstpt', 'type' => 'port', 'city' => 'Testville',
            'country' => 'Indonesia', 'country_code' => 'id', 'region' => 'sea',
            'latitude' => -6.1, 'longitude' => 106.8,
        ], $override);
    }

    public function test_operator_can_read_but_not_create(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Operator));

        $this->getJson('/api/locations')->assertOk();
        $this->postJson('/api/locations', $this->payload())->assertForbidden();
    }

    public function test_customer_is_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Customer));

        $this->getJson('/api/locations')->assertForbidden();
    }

    public function test_admin_can_create_location_with_normalised_codes(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        Sanctum::actingAs($admin);

        $this->postJson('/api/locations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.code', 'TSTPT')
            ->assertJsonPath('data.country_code', 'ID')
            ->assertJsonPath('data.region', 'SEA');

        $this->assertDatabaseHas('audit_logs', ['action' => 'location.created', 'user_id' => $admin->id]);
    }

    public function test_coordinates_are_validated(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Admin));

        $this->postJson('/api/locations', $this->payload(['latitude' => 123, 'longitude' => 999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_unknown_region_and_duplicate_code_are_rejected(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Admin));
        Location::create(['name' => 'Dup', 'code' => 'TSTPT', 'city' => 'X', 'country' => 'Y', 'country_code' => 'ID', 'latitude' => 1, 'longitude' => 1]);

        $this->postJson('/api/locations', $this->payload(['region' => 'MARS']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['region', 'code']);
    }

    public function test_list_can_be_filtered_by_region_and_search(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Operator));
        Location::create(['name' => 'Jakarta Port', 'code' => 'IDJKT', 'city' => 'Jakarta', 'country' => 'Indonesia', 'country_code' => 'ID', 'region' => 'SEA', 'latitude' => -6.1, 'longitude' => 106.8]);
        Location::create(['name' => 'Rotterdam Port', 'code' => 'NLRTM', 'city' => 'Rotterdam', 'country' => 'Netherlands', 'country_code' => 'NL', 'region' => 'EUR', 'latitude' => 51.9, 'longitude' => 4.1]);

        $this->getJson('/api/locations?region=eur')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'NLRTM');
        $this->getJson('/api/locations?search=jakarta')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_location_used_by_shipment_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Admin));
        $shipment = $this->makeShipment($this->makeUser(UserRole::Customer));

        $this->deleteJson("/api/locations/{$shipment->origin_location_id}")->assertStatus(409);
        $this->assertDatabaseHas('locations', ['id' => $shipment->origin_location_id]);
    }

    public function test_unused_location_can_be_deleted(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Admin));
        $location = Location::create(['name' => 'Spare', 'code' => 'SPARE', 'city' => 'X', 'country' => 'Y', 'country_code' => 'ID', 'latitude' => 1, 'longitude' => 1]);

        $this->deleteJson("/api/locations/{$location->id}")->assertNoContent();
        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }
}

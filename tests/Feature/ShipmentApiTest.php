<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCargoData;
use Tests\TestCase;

class ShipmentApiTest extends TestCase
{
    use RefreshDatabase, CreatesCargoData;

    private function payload($customer, array $override = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'carrier_id' => $this->makeCarrier()->id,
            'origin_location_id' => $this->makeLocation('Origin')->id,
            'destination_location_id' => $this->makeLocation('Destination')->id,
            'scenario' => 'A',
            'cargo_type' => 'Garments',
            'expected_departure_at' => now()->addDays(5)->toIso8601String(),
            'expected_arrival_at' => now()->addDays(35)->toIso8601String(),
            'items' => [
                ['description' => 'Shirts', 'hs_code' => '6109', 'quantity' => 100, 'weight_kg' => 250.5, 'volume_cbm' => 3.2],
                ['description' => 'Jackets', 'quantity' => 50, 'weight_kg' => 120, 'volume_cbm' => 2],
            ],
        ], $override);
    }

    // ---- creation -------------------------------------------------------

    public function test_operator_creates_shipment_with_totals_number_and_first_event(): void
    {
        $operator = $this->makeUser(UserRole::Operator);
        $customer = $this->makeUser(UserRole::Customer);
        Sanctum::actingAs($operator);

        $res = $this->postJson('/api/shipments', $this->payload($customer))->assertCreated();

        $res->assertJsonPath('data.status', 'CREATED')
            ->assertJsonPath('data.total_weight_kg', 370.5)
            ->assertJsonPath('data.total_volume_cbm', 5.2)
            ->assertJsonPath('data.delay.is_delayed', false);

        $this->assertMatchesRegularExpression('/^CT-\d{4}-\d{6}$/', $res->json('data.shipment_number'));
        $this->assertDatabaseCount('shipment_items', 2);
        $this->assertDatabaseHas('tracking_events', ['event_type' => 'CREATED', 'status' => 'CREATED']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.created', 'user_id' => $operator->id]);
    }

    public function test_customer_cannot_create_shipment(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        Sanctum::actingAs($customer);

        $this->postJson('/api/shipments', $this->payload($customer))->assertForbidden();
    }

    public function test_creation_validation_rules(): void
    {
        $operator = $this->makeUser(UserRole::Operator);
        $customer = $this->makeUser(UserRole::Customer);
        Sanctum::actingAs($operator);

        $location = $this->makeLocation('Same');

        // same origin and destination
        $this->postJson('/api/shipments', $this->payload($customer, [
            'origin_location_id' => $location->id, 'destination_location_id' => $location->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('destination_location_id');

        // arrival before departure
        $this->postJson('/api/shipments', $this->payload($customer, [
            'expected_arrival_at' => now()->addDay()->toIso8601String(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('expected_arrival_at');

        // no items
        $this->postJson('/api/shipments', $this->payload($customer, ['items' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        // customer_id must be a customer account, not staff
        $this->postJson('/api/shipments', $this->payload($customer, ['customer_id' => $operator->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');

        // inactive carrier
        $inactive = $this->makeCarrier(['is_active' => false]);
        $this->postJson('/api/shipments', $this->payload($customer, ['carrier_id' => $inactive->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('carrier_id');
    }

    // ---- visibility -----------------------------------------------------

    public function test_customer_only_sees_own_shipments(): void
    {
        $a = $this->makeUser(UserRole::Customer);
        $b = $this->makeUser(UserRole::Customer);
        $mine = $this->makeShipment($a);
        $theirs = $this->makeShipment($b);

        Sanctum::actingAs($a);

        $this->getJson('/api/shipments')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/shipments/{$mine->id}")->assertOk();
        $this->getJson("/api/shipments/{$theirs->id}")->assertForbidden();
    }

    public function test_operator_sees_all_and_can_filter(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $this->makeShipment($customer, ['scenario' => 'A']);
        $this->makeShipment($customer, ['scenario' => 'B', 'is_delayed' => true, 'delay_minutes' => 90]);
        $this->makeShipment($customer, ['scenario' => 'C', 'current_status' => ShipmentStatus::Delivered]);

        Sanctum::actingAs($this->makeUser(UserRole::Operator));

        $this->getJson('/api/shipments')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/shipments?scenario=B')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/shipments?is_delayed=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/shipments?status=DELIVERED')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/shipments?status=NONSENSE')->assertUnprocessable();
        $this->getJson('/api/shipments?per_page=500')->assertUnprocessable();
    }

    public function test_search_by_shipment_number(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $this->makeShipment($customer, ['shipment_number' => 'CT-2026-000777']);
        $this->makeShipment($customer, ['shipment_number' => 'CT-2026-000888']);

        Sanctum::actingAs($this->makeUser(UserRole::Admin));

        $this->getJson('/api/shipments?search=777')->assertOk()->assertJsonCount(1, 'data');
    }

    // ---- update / cancel / delete --------------------------------------

    public function test_update_allowed_before_pickup_and_blocked_after(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        Sanctum::actingAs($this->makeUser(UserRole::Operator));

        $open = $this->makeShipment($customer, ['current_status' => ShipmentStatus::Booked]);
        $this->putJson("/api/shipments/{$open->id}", ['cargo_type' => 'Electronics'])
            ->assertOk()->assertJsonPath('data.cargo_type', 'Electronics');

        $picked = $this->makeShipment($customer, ['current_status' => ShipmentStatus::PickedUp]);
        $this->putJson("/api/shipments/{$picked->id}", ['cargo_type' => 'Electronics'])->assertStatus(409);
    }

    public function test_immutable_fields_cannot_be_changed_on_update(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $shipment = $this->makeShipment($customer);
        Sanctum::actingAs($this->makeUser(UserRole::Operator));

        $this->putJson("/api/shipments/{$shipment->id}", ['customer_id' => $this->makeUser(UserRole::Customer)->id])
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    }

    public function test_cancel_before_pickup_only(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        Sanctum::actingAs($this->makeUser(UserRole::Operator));

        $created = $this->makeShipment($customer);
        $this->postJson("/api/shipments/{$created->id}/cancel", ['reason' => 'Customer request'])
            ->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $created->id, 'event_type' => 'CANCELLED']);

        $moving = $this->makeShipment($customer, ['current_status' => ShipmentStatus::InTransit]);
        $this->postJson("/api/shipments/{$moving->id}/cancel")->assertStatus(409);
    }

    public function test_only_admin_can_delete_and_only_unstarted_shipments(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $shipment = $this->makeShipment($customer);
        $moving = $this->makeShipment($customer, ['current_status' => ShipmentStatus::InTransit]);

        Sanctum::actingAs($this->makeUser(UserRole::Operator));
        $this->deleteJson("/api/shipments/{$shipment->id}")->assertForbidden();

        Sanctum::actingAs($this->makeUser(UserRole::Admin));
        $this->deleteJson("/api/shipments/{$moving->id}")->assertStatus(409);
        $this->deleteJson("/api/shipments/{$shipment->id}")->assertNoContent();

        $this->assertSoftDeleted('shipments', ['id' => $shipment->id]);
        $this->getJson("/api/shipments/{$shipment->id}")->assertNotFound();
    }
}

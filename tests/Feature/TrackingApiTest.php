<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCargoData;
use Tests\TestCase;

class TrackingApiTest extends TestCase
{
    use RefreshDatabase, CreatesCargoData;

    private function post_event($shipment, string $type, array $extra = [])
    {
        $location = $this->makeLocation($type);

        return $this->postJson("/api/shipments/{$shipment->id}/tracking-events", array_merge([
            'event_type' => $type,
            'location_id' => $location->id,
        ], $extra));
    }

    private function operatorAndShipment(array $overrides = []): array
    {
        $operator = $this->makeUser(UserRole::Operator);
        $customer = $this->makeUser(UserRole::Customer);
        Sanctum::actingAs($operator);

        return [$operator, $customer, $this->makeShipment($customer, $overrides)];
    }

    // ---- happy path ------------------------------------------------------

    public function test_full_lifecycle_in_order(): void
    {
        $this->freezeTime();
        [, , $shipment] = $this->operatorAndShipment();

        $steps = [
            ['BOOKED', 'BOOKED'],
            ['PICKED_UP', 'PICKED_UP'],
            ['DEPARTED_PORT', 'IN_TRANSIT'],
            ['TRANSSHIPMENT', 'IN_TRANSIT'],
            ['ARRIVED_PORT', 'IN_TRANSIT'],
            ['CUSTOMS_CLEARANCE', 'IN_TRANSIT'],
            ['DELIVERED', 'DELIVERED'],
        ];

        foreach ($steps as $i => [$type, $expectedStatus]) {
            $this->post_event($shipment, $type, ['occurred_at' => now()->subMinutes(100 - $i)->toIso8601String()])
                ->assertCreated()
                ->assertJsonPath('data.event_type', $type)
                ->assertJsonPath('shipment.status', $expectedStatus);
        }

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Delivered, $shipment->current_status);
        $this->assertNotNull($shipment->actual_departure_at);
        $this->assertNotNull($shipment->actual_arrival_at);
        $this->assertDatabaseCount('tracking_events', 7);
    }

    // ---- state machine ---------------------------------------------------

    public function test_cannot_skip_statuses(): void
    {
        [, , $shipment] = $this->operatorAndShipment(); // status CREATED

        $this->post_event($shipment, 'DEPARTED_PORT')->assertStatus(409);
        $this->post_event($shipment, 'DELIVERED')->assertStatus(409);

        $this->assertSame(ShipmentStatus::Created, $shipment->refresh()->current_status);
        $this->assertDatabaseCount('tracking_events', 0);
    }

    public function test_cannot_repeat_or_go_backwards(): void
    {
        [, , $shipment] = $this->operatorAndShipment(['current_status' => ShipmentStatus::Booked]);

        $this->post_event($shipment, 'BOOKED')->assertStatus(409);
    }

    public function test_in_transit_events_rejected_before_departure(): void
    {
        [, , $shipment] = $this->operatorAndShipment(['current_status' => ShipmentStatus::PickedUp]);

        $this->post_event($shipment, 'TRANSSHIPMENT')->assertStatus(409);
        $this->post_event($shipment, 'CUSTOMS_CLEARANCE')->assertStatus(409);
    }

    public function test_finished_shipments_are_closed(): void
    {
        [, , $delivered] = $this->operatorAndShipment(['current_status' => ShipmentStatus::Delivered]);
        $this->post_event($delivered, 'TRANSSHIPMENT')->assertStatus(409);

        $cancelled = $this->makeShipment($this->makeUser(UserRole::Customer), ['current_status' => ShipmentStatus::Cancelled]);
        $this->post_event($cancelled, 'BOOKED')->assertStatus(409);
    }

    public function test_system_event_types_cannot_be_posted_manually(): void
    {
        [, , $shipment] = $this->operatorAndShipment();

        foreach (['CREATED', 'CANCELLED', 'EXCEPTION'] as $type) {
            $this->post_event($shipment, $type)->assertUnprocessable()->assertJsonValidationErrors('event_type');
        }
    }

    // ---- chronology ------------------------------------------------------

    public function test_events_must_be_chronological_and_not_in_the_future(): void
    {
        $this->freezeTime();
        [, , $shipment] = $this->operatorAndShipment();

        $this->post_event($shipment, 'BOOKED', ['occurred_at' => now()->subHours(2)->toIso8601String()])->assertCreated();

        $this->post_event($shipment, 'PICKED_UP', ['occurred_at' => now()->subHours(5)->toIso8601String()])
            ->assertUnprocessable();
        $this->post_event($shipment, 'PICKED_UP', ['occurred_at' => now()->addDays(2)->toIso8601String()])
            ->assertUnprocessable();
    }

    // ---- ETA / delay rules ----------------------------------------------

    public function test_late_departure_pushes_eta_and_flags_delay(): void
    {
        $this->freezeTime();
        [, , $shipment] = $this->operatorAndShipment([
            'current_status' => ShipmentStatus::PickedUp,
            'scenario' => 'A',
            'expected_departure_at' => now()->subDays(3),
            'expected_arrival_at' => now()->addDays(20),
        ]);

        // planned to leave 3 days ago, actually leaves 1 hour ago => 71 hours late
        $this->post_event($shipment, 'DEPARTED_PORT', ['occurred_at' => now()->subHour()->toIso8601String()])
            ->assertCreated()
            ->assertJsonPath('shipment.delay.is_delayed', true)
            ->assertJsonPath('shipment.delay.minutes', 71 * 60)
            ->assertJsonPath('shipment.delay.severity', 'HIGH'); // 71h > 24h (A: 12h threshold)
    }

    public function test_explicit_eta_from_operator_is_used(): void
    {
        $this->freezeTime();
        [, , $shipment] = $this->operatorAndShipment([
            'current_status' => ShipmentStatus::InTransit,
            'expected_arrival_at' => now()->addDays(10),
        ]);

        $newEta = now()->addDays(10)->addHours(5); // 5h late => LOW for scenario A

        $this->post_event($shipment, 'TRANSSHIPMENT', ['estimated_arrival_at' => $newEta->toIso8601String()])
            ->assertCreated()
            ->assertJsonPath('shipment.delay.minutes', 300)
            ->assertJsonPath('shipment.delay.severity', 'LOW');
    }

    public function test_eta_cannot_be_before_the_event_time(): void
    {
        $this->freezeTime();
        [, , $shipment] = $this->operatorAndShipment(['current_status' => ShipmentStatus::InTransit]);

        $this->post_event($shipment, 'TRANSSHIPMENT', ['estimated_arrival_at' => now()->subDay()->toIso8601String()])
            ->assertUnprocessable();
    }

    public function test_late_delivery_severity_depends_on_the_scenario(): void
    {
        $this->freezeTime();

        foreach (['A' => 'HIGH', 'C' => 'MEDIUM'] as $scenario => $severity) {
            [, , $shipment] = $this->operatorAndShipment([
                'current_status' => ShipmentStatus::InTransit,
                'scenario' => $scenario,
                'expected_departure_at' => now()->subDays(10),
                'expected_arrival_at' => now()->subHours(30), // due 30h ago, delivered now
            ]);

            $this->post_event($shipment, 'DELIVERED', ['occurred_at' => now()->toIso8601String()])
                ->assertCreated()
                ->assertJsonPath('shipment.delay.minutes', 30 * 60)
                ->assertJsonPath('shipment.delay.severity', $severity);
        }
    }

    public function test_on_time_delivery_is_not_delayed(): void
    {
        $this->freezeTime();
        [, , $shipment] = $this->operatorAndShipment([
            'current_status' => ShipmentStatus::InTransit,
            'expected_arrival_at' => now()->addHours(2),
        ]);

        $this->post_event($shipment, 'DELIVERED', ['occurred_at' => now()->toIso8601String()])
            ->assertCreated()
            ->assertJsonPath('shipment.delay.is_delayed', false)
            ->assertJsonPath('shipment.delay.severity', null);
    }

    // ---- access + timeline ----------------------------------------------

    public function test_customer_cannot_record_events(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $shipment = $this->makeShipment($customer);
        Sanctum::actingAs($customer);

        $this->post_event($shipment, 'BOOKED')->assertForbidden();
    }

    public function test_customer_sees_own_timeline_only_and_in_order(): void
    {
        $this->freezeTime();
        $customer = $this->makeUser(UserRole::Customer);
        $other = $this->makeUser(UserRole::Customer);
        $mine = $this->makeShipment($customer);
        $theirs = $this->makeShipment($other);

        Sanctum::actingAs($this->makeUser(UserRole::Operator));
        $this->post_event($mine, 'BOOKED', ['occurred_at' => now()->subHours(3)->toIso8601String()])->assertCreated();
        $this->post_event($mine, 'PICKED_UP', ['occurred_at' => now()->subHours(1)->toIso8601String()])->assertCreated();

        Sanctum::actingAs($customer);
        $this->getJson("/api/shipments/{$mine->id}/tracking")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.event_type', 'BOOKED')
            ->assertJsonPath('data.1.event_type', 'PICKED_UP');

        $this->getJson("/api/shipments/{$theirs->id}/tracking")->assertForbidden();
    }

    public function test_status_change_is_written_to_audit_log(): void
    {
        [$operator, , $shipment] = $this->operatorAndShipment();

        $this->post_event($shipment, 'BOOKED')->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'shipment.status_changed',
            'user_id' => $operator->id,
            'auditable_id' => $shipment->id,
        ]);
    }
}

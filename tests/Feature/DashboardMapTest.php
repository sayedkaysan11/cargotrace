<?php

namespace Tests\Feature;

use App\Enums\TrackingEventType;
use App\Enums\UserRole;
use App\Models\TrackingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCargoData;
use Tests\TestCase;

class DashboardMapTest extends TestCase
{
    use CreatesCargoData, RefreshDatabase;

    public function test_guest_cannot_access_dashboard_map_data(): void
    {
        $this->get(route('dashboard.map-data'))->assertRedirect('/login');
    }

    public function test_map_data_is_limited_to_customer_shipments_and_only_reports_recorded_gps(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $otherCustomer = $this->makeUser(UserRole::Customer);
        $shipmentWithGps = $this->makeShipment($customer);
        $shipmentWithoutGps = $this->makeShipment($customer);
        $otherShipment = $this->makeShipment($otherCustomer);

        TrackingEvent::create([
            'shipment_id' => $shipmentWithGps->id,
            'event_type' => TrackingEventType::InTransit,
            'status' => $shipmentWithGps->current_status,
            'occurred_at' => now()->subMinutes(12),
            'latitude' => 35.6762,
            'longitude' => 139.6503,
            'description' => 'Recorded coordinate test',
        ]);

        $response = $this->actingAs($customer)
            ->getJson(route('dashboard.map-data'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $data = collect($response->json('data'))->keyBy('id');

        $this->assertCount(2, $data);
        $this->assertArrayNotHasKey($otherShipment->id, $data->all());
        $this->assertSame(
            ['latitude' => 35.6762, 'longitude' => 139.6503],
            collect($data[$shipmentWithGps->id]['position'])->only(['latitude', 'longitude'])->all(),
        );
        $this->assertNull($data[$shipmentWithoutGps->id]['position']);
        $this->assertSame(route('tracking.show', $shipmentWithGps), $data[$shipmentWithGps->id]['url']);
        $this->assertArrayNotHasKey('description', $data[$shipmentWithGps->id]);
        $this->assertFalse($data[$shipmentWithoutGps->id]['position'] !== null);
    }

    public function test_map_status_filter_returns_only_matching_visible_shipments(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $transitShipment = $this->makeShipment($customer, ['current_status' => 'IN_TRANSIT']);
        $this->makeShipment($customer, ['current_status' => 'DELIVERED']);

        $this->actingAs($customer)
            ->getJson(route('dashboard.map-data', ['status' => 'in_transit']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $transitShipment->id)
            ->assertJsonPath('data.0.status_key', 'in_transit');
    }

    public function test_map_data_rejects_unknown_status_filters(): void
    {
        $this->actingAs($this->makeUser(UserRole::Customer))
            ->getJson(route('dashboard.map-data', ['status' => 'invalid']))
            ->assertUnprocessable();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ExceptionSeverity;
use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\TrackingEventType;
use App\Enums\UserRole;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCargoData;
use Tests\TestCase;

class PortalPagesTest extends TestCase
{
    use CreatesCargoData, RefreshDatabase;

    public function test_authenticated_users_can_open_the_portal_pages(): void
    {
        $operator = $this->makeUser(UserRole::Operator);
        $shipment = $this->makeShipment($this->makeUser(UserRole::Customer));

        TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'event_type' => TrackingEventType::Created,
            'status' => $shipment->current_status,
            'occurred_at' => now(),
            'description' => 'Shipment created',
        ]);

        $this->actingAs($operator);

        foreach ([
            '/shipments',
            '/shipments/'.$shipment->id,
            '/tracking/'.$shipment->id,
            '/map',
            '/exceptions',
            '/reports',
            '/analytics',
            '/news',
            '/settings',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/news/'.$shipment->trackingEvents()->first()->id)->assertOk();
    }

    public function test_admin_user_directory_is_forbidden_to_non_admins(): void
    {
        $this->actingAs($this->makeUser(UserRole::Operator))
            ->get('/users')
            ->assertForbidden();
    }

    public function test_admin_can_filter_users_by_active_status(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $activeUser = $this->makeUser(UserRole::Operator, ['name' => 'Active Operator']);
        $inactiveUser = $this->makeUser(UserRole::Operator, ['name' => 'Inactive Operator', 'is_active' => false]);

        $this->actingAs($admin)
            ->get('/users?is_active=1')
            ->assertOk()
            ->assertSee($activeUser->name)
            ->assertDontSee($inactiveUser->name);

        $this->get('/users?is_active=0')
            ->assertOk()
            ->assertSee($inactiveUser->name)
            ->assertDontSee($activeUser->name);
    }

    public function test_customer_shipment_list_and_details_are_scoped_to_the_customer(): void
    {
        $customer = $this->makeUser(UserRole::Customer, ['company_name' => 'Own Cargo Ltd']);
        $otherCustomer = $this->makeUser(UserRole::Customer, ['company_name' => 'Other Cargo Ltd']);
        $ownShipment = $this->makeShipment($customer);
        $otherShipment = $this->makeShipment($otherCustomer);

        $this->actingAs($customer)
            ->get('/shipments')
            ->assertOk()
            ->assertSee($ownShipment->shipment_number)
            ->assertDontSee($otherShipment->shipment_number);

        $this->get('/shipments?search=Own%20Cargo')
            ->assertOk()
            ->assertSee($ownShipment->shipment_number)
            ->assertDontSee($otherShipment->shipment_number);

        $this->get('/shipments/'.$otherShipment->id)->assertForbidden();
    }

    public function test_operator_can_create_a_shipment_and_initial_tracking_event(): void
    {
        $operator = $this->makeUser(UserRole::Operator);
        $customer = $this->makeUser(UserRole::Customer);
        $carrier = $this->makeCarrier(['is_active' => true]);
        $origin = $this->makeLocation('Jakarta');
        $destination = $this->makeLocation('Singapore');

        $this->actingAs($operator)
            ->post('/shipments', [
                'customer_id' => $customer->id,
                'carrier_id' => $carrier->id,
                'origin_location_id' => $origin->id,
                'destination_location_id' => $destination->id,
                'scenario' => 'A',
                'cargo_type' => 'Electronics',
                'expected_departure_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'expected_arrival_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
                'items' => [[
                    'description' => 'Electronic goods',
                    'quantity' => 3,
                    'weight_kg' => 50,
                    'volume_cbm' => 2.5,
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shipments', [
            'customer_id' => $customer->id,
            'carrier_id' => $carrier->id,
            'cargo_type' => 'Electronics',
            'current_status' => 'CREATED',
        ]);
        $this->assertDatabaseHas('tracking_events', [
            'location_id' => $origin->id,
            'event_type' => TrackingEventType::Created->value,
        ]);
    }

    public function test_operator_can_record_a_tracking_update_from_the_shipment_page(): void
    {
        $operator = $this->makeUser(UserRole::Operator);
        $shipment = $this->makeShipment($this->makeUser(UserRole::Customer));

        $this->actingAs($operator)
            ->post('/shipments/'.$shipment->id.'/tracking-events', [
                'event_type' => TrackingEventType::Booked->value,
                'description' => 'Booking confirmed',
            ])
            ->assertRedirect();

        $this->assertSame('BOOKED', $shipment->refresh()->current_status->value);
        $this->assertDatabaseHas('tracking_events', [
            'shipment_id' => $shipment->id,
            'event_type' => TrackingEventType::Booked->value,
        ]);
    }

    public function test_user_can_update_their_account_settings(): void
    {
        $user = $this->makeUser(UserRole::Customer);

        $this->actingAs($user)
            ->put('/settings', [
                'name' => 'Updated Customer',
                'email' => $user->email,
                'company_name' => 'Updated Company',
                'phone' => '08123456789',
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Customer',
            'company_name' => 'Updated Company',
            'phone' => '08123456789',
        ]);
    }

    public function test_exceptions_page_shows_only_visible_shipments(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $otherCustomer = $this->makeUser(UserRole::Customer);
        $ownShipment = $this->makeShipment($customer);
        $otherShipment = $this->makeShipment($otherCustomer);

        $ownException = ShipmentException::create([
            'shipment_id' => $ownShipment->id,
            'type' => ExceptionType::WeatherDelay,
            'severity' => ExceptionSeverity::Medium,
            'status' => ExceptionStatus::Open,
            'description' => 'Weather delay',
            'detected_at' => now(),
        ]);
        ShipmentException::create([
            'shipment_id' => $otherShipment->id,
            'type' => ExceptionType::PortCongestion,
            'severity' => ExceptionSeverity::High,
            'status' => ExceptionStatus::Open,
            'description' => 'Port congestion',
            'detected_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get('/exceptions')
            ->assertOk()
            ->assertSee($ownException->description)
            ->assertDontSee('Port congestion');
    }

    public function test_map_markers_can_select_a_shipment_and_show_its_last_gps_position(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $otherCustomer = $this->makeUser(UserRole::Customer);
        $shipment = $this->makeShipment($customer);
        $otherShipment = $this->makeShipment($otherCustomer);
        $nearbyShipment = $this->makeShipment($customer, [
            'origin_location_id' => $shipment->origin_location_id,
        ]);

        TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'location_id' => $shipment->origin_location_id,
            'event_type' => TrackingEventType::InTransit,
            'status' => $shipment->current_status,
            'occurred_at' => now()->subMinutes(7),
            'latitude' => -6.12345,
            'longitude' => 106.54321,
        ]);
        TrackingEvent::create([
            'shipment_id' => $nearbyShipment->id,
            'location_id' => $nearbyShipment->origin_location_id,
            'event_type' => TrackingEventType::InTransit,
            'status' => $nearbyShipment->current_status,
            'occurred_at' => now()->subMinutes(6),
            'latitude' => -6.12345,
            'longitude' => 106.54321,
        ]);

        $this->actingAs($customer)
            ->get('/map')
            ->assertOk()
            ->assertSee('Pilih kapal '.$shipment->shipment_number)
            ->assertSee('data-map-x=', false)
            ->assertDontSee('Pilih kapal '.$otherShipment->shipment_number);

        $this->get('/map?selected='.$shipment->id)
            ->assertOk()
            ->assertSee($shipment->shipment_number)
            ->assertSee('-6.12345', false)
            ->assertSee('106.54321', false)
            ->assertSee('GPS terakhir tercatat');

        $this->get('/map?selected='.$otherShipment->id)
            ->assertOk()
            ->assertDontSee($otherShipment->shipment_number);
    }

    public function test_news_cards_have_local_images_and_open_a_scoped_detail_page(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $otherCustomer = $this->makeUser(UserRole::Customer);
        $shipment = $this->makeShipment($customer);
        $otherShipment = $this->makeShipment($otherCustomer);
        $event = TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'event_type' => TrackingEventType::DepartedPort,
            'status' => $shipment->current_status,
            'occurred_at' => now(),
            'description' => 'Vessel departed the port.',
        ]);
        $otherEvent = TrackingEvent::create([
            'shipment_id' => $otherShipment->id,
            'event_type' => TrackingEventType::Exception,
            'status' => $otherShipment->current_status,
            'is_exception' => true,
            'occurred_at' => now(),
            'description' => 'Private shipment update.',
        ]);

        $this->actingAs($customer)
            ->get('/news')
            ->assertOk()
            ->assertSee('/images/news/port-terminal.svg')
            ->assertSee(route('news.show', $event), false)
            ->assertDontSee('Private shipment update.');

        $this->get(route('news.show', $event))
            ->assertOk()
            ->assertSee('/images/news/port-terminal.svg')
            ->assertSee($event->description);

        $this->get(route('news.show', $otherEvent))->assertNotFound();
    }
}

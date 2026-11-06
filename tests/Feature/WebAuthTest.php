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

class WebAuthTest extends TestCase
{
    use CreatesCargoData, RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_login_and_see_dashboard(): void
    {
        $user = $this->makeUser(UserRole::Operator);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('Total Shipment')
            ->assertSee('On-Time Delivery')
            ->assertSee('Shipment Analytics')
            ->assertSee('Top Carriers')
            ->assertSee('Tracking Timeline')
            ->assertDontSee('Halaman placeholder');
    }

    public function test_customer_dashboard_only_shows_their_own_shipments(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $otherCustomer = $this->makeUser(UserRole::Customer);
        $ownShipment = $this->makeShipment($customer);
        $otherShipment = $this->makeShipment($otherCustomer);

        $this->actingAs($customer)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($ownShipment->shipment_number)
            ->assertDontSee($otherShipment->shipment_number);
    }

    public function test_dashboard_renders_the_interactive_geographic_shipment_map(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $this->makeShipment($customer);

        $this->actingAs($customer)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Interactive geographic map of shipment routes')
            ->assertSee(route('dashboard.map-data'), false)
            ->assertSee('Recorded GPS');
    }

    public function test_dashboard_on_time_delivery_rate_uses_only_delivered_shipments(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $deliveredShipment = $this->makeShipment($customer, ['current_status' => 'DELIVERED', 'is_delayed' => false]);
        $this->makeShipment($customer, ['current_status' => 'DELIVERED', 'is_delayed' => true]);
        $this->makeShipment($customer, ['current_status' => 'IN_TRANSIT', 'is_delayed' => false]);

        $this->actingAs($customer)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('On-Time Delivery')
            ->assertSee('50.0%')
            ->assertSee('/tracking/'.$deliveredShipment->id, false);
    }

    public function test_dashboard_shipment_filters_and_pagination_use_visible_database_records(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $matchingShipment = $this->makeShipment($customer, ['current_status' => 'IN_TRANSIT']);
        $this->makeShipment($customer);
        foreach (range(1, 4) as $_) {
            $this->makeShipment($customer);
        }

        $this->actingAs($customer)
            ->get('/dashboard?period=30d&status=IN_TRANSIT&search='.$matchingShipment->shipment_number)
            ->assertOk()
            ->assertViewHas('period', '30d')
            ->assertViewHas('shipments', fn ($shipments): bool => $shipments->total() === 1
                && $shipments->count() === 1
                && $shipments->first()->is($matchingShipment));

        $this->get('/dashboard?page=2')
            ->assertOk()
            ->assertViewHas('shipments', fn ($shipments): bool => $shipments->total() === 6
                && $shipments->count() === 1
                && $shipments->currentPage() === 2);
    }

    public function test_dashboard_counts_unique_shipments_affected_by_exceptions(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $shipment = $this->makeShipment($customer, ['is_delayed' => true]);
        foreach ([ExceptionType::WeatherDelay, ExceptionType::PortCongestion] as $type) {
            ShipmentException::create([
                'shipment_id' => $shipment->id,
                'type' => $type,
                'severity' => ExceptionSeverity::Medium,
                'status' => ExceptionStatus::Open,
                'description' => 'Dashboard exception metric test',
                'detected_at' => now(),
            ]);
        }

        $this->actingAs($customer)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('exceptionShipmentCount', 1)
            ->assertViewHas('totalExceptionCount', 2)
            ->assertViewHas('delayedCount', 1);
    }

    public function test_dashboard_analytics_handles_tracking_event_enum_casts(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $shipment = $this->makeShipment($customer);
        TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'event_type' => TrackingEventType::Created,
            'status' => $shipment->current_status,
            'occurred_at' => now(),
            'description' => 'Shipment created',
        ]);

        $this->actingAs($customer)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('analyticsData', fn (array $analyticsData): bool => array_sum($analyticsData['series']['Created']) === 1);
    }

    public function test_invalid_login_shows_error(): void
    {
        $user = $this->makeUser(UserRole::Customer);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $user = $this->makeUser(UserRole::Customer);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}

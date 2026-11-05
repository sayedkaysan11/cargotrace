<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCargoData;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase, CreatesCargoData;

    public function test_customer_can_view_only_own_shipment(): void
    {
        $owner = $this->makeUser(UserRole::Customer);
        $other = $this->makeUser(UserRole::Customer);
        $shipment = $this->makeShipment($owner);

        $this->assertTrue($owner->can('view', $shipment));
        $this->assertFalse($other->can('view', $shipment));
    }

    public function test_operator_and_admin_can_view_any_shipment(): void
    {
        $shipment = $this->makeShipment($this->makeUser(UserRole::Customer));

        $this->assertTrue($this->makeUser(UserRole::Operator)->can('view', $shipment));
        $this->assertTrue($this->makeUser(UserRole::Admin)->can('view', $shipment));
    }

    public function test_only_admin_and_operator_can_create_or_update(): void
    {
        $shipment = $this->makeShipment($this->makeUser(UserRole::Customer));
        $customer = $this->makeUser(UserRole::Customer);

        $this->assertFalse($customer->can('create', Shipment::class));
        $this->assertFalse($customer->can('update', $shipment));
        $this->assertTrue($this->makeUser(UserRole::Operator)->can('create', Shipment::class));
        $this->assertTrue($this->makeUser(UserRole::Admin)->can('update', $shipment));
    }

    public function test_only_admin_can_delete(): void
    {
        $shipment = $this->makeShipment($this->makeUser(UserRole::Customer));

        $this->assertFalse($this->makeUser(UserRole::Operator)->can('delete', $shipment));
        $this->assertTrue($this->makeUser(UserRole::Admin)->can('delete', $shipment));
    }

    public function test_visible_to_scope_limits_customers(): void
    {
        $a = $this->makeUser(UserRole::Customer);
        $b = $this->makeUser(UserRole::Customer);
        $this->makeShipment($a);
        $this->makeShipment($b);
        $this->makeShipment($b);

        $this->assertSame(1, Shipment::visibleTo($a)->count());
        $this->assertSame(2, Shipment::visibleTo($b)->count());
        $this->assertSame(3, Shipment::visibleTo($this->makeUser(UserRole::Operator))->count());
    }

    public function test_role_middleware_blocks_customer_with_403(): void
    {
        Route::middleware(['auth:sanctum', 'role:admin,operator'])
            ->get('/api/_role-check', fn () => response()->json(['ok' => true]));

        Sanctum::actingAs($this->makeUser(UserRole::Customer));

        $this->getJson('/api/_role-check')->assertForbidden();
    }

    public function test_role_middleware_allows_operator(): void
    {
        Route::middleware(['auth:sanctum', 'role:admin,operator'])
            ->get('/api/_role-check', fn () => response()->json(['ok' => true]));

        Sanctum::actingAs($this->makeUser(UserRole::Operator));

        $this->getJson('/api/_role-check')->assertOk();
    }
}

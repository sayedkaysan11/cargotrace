<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Carrier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCargoData;
use Tests\TestCase;

class CarrierApiTest extends TestCase
{
    use RefreshDatabase, CreatesCargoData;

    private function payload(array $override = []): array
    {
        return array_merge(['name' => 'Pacific Star Line', 'code' => 'psl', 'contact_email' => 'ops@psl.example'], $override);
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/carriers')->assertUnauthorized();
    }

    public function test_customer_cannot_access_carriers(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Customer));

        $this->getJson('/api/carriers')->assertForbidden();
    }

    public function test_operator_can_read_but_not_write(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Operator));
        $carrier = Carrier::create(['name' => 'Existing', 'code' => 'EXI']);

        $this->getJson('/api/carriers')->assertOk();
        $this->getJson("/api/carriers/{$carrier->id}")->assertOk();
        $this->postJson('/api/carriers', $this->payload())->assertForbidden();
        $this->putJson("/api/carriers/{$carrier->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->deleteJson("/api/carriers/{$carrier->id}")->assertForbidden();
    }

    public function test_admin_can_create_and_audit_log_is_written(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        Sanctum::actingAs($admin);

        $this->postJson('/api/carriers', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.code', 'PSL'); // normalised to upper case

        $this->assertDatabaseHas('carriers', ['code' => 'PSL']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'carrier.created', 'user_id' => $admin->id]);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Admin));
        Carrier::create(['name' => 'Existing', 'code' => 'PSL']);

        $this->postJson('/api/carriers', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_update_records_old_and_new_values(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Admin));
        $carrier = Carrier::create(['name' => 'Old Name', 'code' => 'OLD']);

        $this->putJson("/api/carriers/{$carrier->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $log = AuditLog::where('action', 'carrier.updated')->firstOrFail();
        $this->assertSame('Old Name', $log->old_values['name']);
        $this->assertSame('New Name', $log->new_values['name']);
    }

    public function test_update_allows_keeping_own_code(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Admin));
        $carrier = Carrier::create(['name' => 'Same', 'code' => 'SAME']);

        $this->putJson("/api/carriers/{$carrier->id}", ['code' => 'SAME', 'name' => 'Renamed'])->assertOk();
    }

    public function test_list_supports_search_filter_and_caps_per_page(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Operator));
        Carrier::create(['name' => 'Alpha Cargo', 'code' => 'ALP']);
        Carrier::create(['name' => 'Beta Freight', 'code' => 'BET', 'is_active' => false]);

        $this->getJson('/api/carriers?search=alpha')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/carriers?is_active=0')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/carriers?per_page=9999')->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    public function test_carrier_without_shipments_can_be_deleted(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Admin));
        $carrier = Carrier::create(['name' => 'Unused', 'code' => 'UNU']);

        $this->deleteJson("/api/carriers/{$carrier->id}")->assertNoContent();

        $this->assertDatabaseMissing('carriers', ['id' => $carrier->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'carrier.deleted']);
    }

    public function test_carrier_with_shipments_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->makeUser(UserRole::Admin));
        $shipment = $this->makeShipment($this->makeUser(UserRole::Customer));

        $this->deleteJson("/api/carriers/{$shipment->carrier_id}")->assertStatus(409);

        $this->assertDatabaseHas('carriers', ['id' => $shipment->carrier_id]);
    }
}

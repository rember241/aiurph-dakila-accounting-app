<?php

namespace Modules\Inventory\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Customers\Models\Customer;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoiceLine;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/inventories')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_inventory_index_requires_inventory_read_ability_for_non_admin_members(): void
    {
        [$company, $member] = $this->createCompanyMemberWithRole([
            'sales' => ['read'],
        ]);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->getJson('/api/v1/inventories');

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');
    }

    public function test_inventory_owner_can_create_list_show_update_transition_and_delete_items(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('inventory-owner-company');

        $createResponse = $this->postJson('/api/v1/inventories', [
            'code' => 'FG-1001',
            'name' => 'Finished Good 1001',
            'description' => 'Initial product master record.',
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'quantity_on_hand' => 125,
            'sales_price' => 1500,
            'cost_price' => 1000,
            'pricing_as_of' => '2026-03-24',
            'stock_as_of' => '2026-03-24',
            'reorder_point' => 20,
            'item_type' => 'inventory',
            'category' => 'Finished Goods',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('message', 'Inventory item created')
            ->assertJsonPath('data.company_id', (string) $company->getKey())
            ->assertJsonPath('data.code', 'FG-1001')
            ->assertJsonPath('data.sku', 'FG-1001')
            ->assertJsonPath('data.unit_of_measure', 'PCS')
            ->assertJsonPath('data.quantity_on_hand', 125)
            ->assertJsonPath('data.sales_price', 1500)
            ->assertJsonPath('data.cost_price', 1000)
            ->assertJsonPath('data.stock_value', 125000)
            ->assertJsonPath('data.pricing_as_of', '2026-03-24')
            ->assertJsonPath('data.stock_as_of', '2026-03-24')
            ->assertJsonPath('data.reorder_point', 20)
            ->assertJsonPath('data.is_low_stock', false)
            ->assertJsonPath('data.stock_health', 'healthy')
            ->assertJsonPath('data.item_type', 'inventory');

        $inventoryId = (string) $createResponse->json('data.id');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryId,
            'company_id' => $company->getKey(),
            'code' => 'FG-1001',
            'quantity_on_hand' => 125,
            'sales_price' => 1500,
            'cost_price' => 1000,
            'pricing_as_of' => '2026-03-24 00:00:00',
            'stock_as_of' => '2026-03-24 00:00:00',
            'reorder_point' => 20,
        ]);

        $indexResponse = $this->getJson('/api/v1/inventories?q=finished');

        $indexResponse->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('stats.low_stock_count', 0)
            ->assertJsonPath('data.0.id', $inventoryId)
            ->assertJsonPath('data.0.company_id', (string) $company->getKey());

        $showResponse = $this->getJson("/api/v1/inventories/{$inventoryId}");

        $showResponse->assertOk()
            ->assertJsonPath('data.id', $inventoryId)
            ->assertJsonPath('data.name', 'Finished Good 1001')
            ->assertJsonPath('data.is_low_stock', false);

        $updateResponse = $this->putJson("/api/v1/inventories/{$inventoryId}", [
            'name' => 'Finished Good 1001 Updated',
            'description' => 'Updated product master record.',
            'unit_of_measure' => 'BOX',
            'quantity_on_hand' => 84,
            'sales_price' => 1600,
            'cost_price' => 1125,
            'pricing_as_of' => '2026-03-31',
            'stock_as_of' => '2026-03-31',
            'reorder_point' => 12,
            'category' => 'Distribution',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('message', 'Inventory item updated')
            ->assertJsonPath('data.name', 'Finished Good 1001 Updated')
            ->assertJsonPath('data.unit_of_measure', 'BOX')
            ->assertJsonPath('data.quantity_on_hand', 84)
            ->assertJsonPath('data.sales_price', 1600)
            ->assertJsonPath('data.cost_price', 1125)
            ->assertJsonPath('data.stock_value', 94500)
            ->assertJsonPath('data.pricing_as_of', '2026-03-31')
            ->assertJsonPath('data.stock_as_of', '2026-03-31')
            ->assertJsonPath('data.reorder_point', 12)
            ->assertJsonPath('data.category', 'Distribution');

        $transitionResponse = $this->patchJson("/api/v1/inventories/{$inventoryId}/status", [
            'status' => 'inactive',
        ]);

        $transitionResponse->assertOk()
            ->assertJsonPath('message', 'Inventory item status updated')
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.is_active', false);

        $deleteResponse = $this->deleteJson("/api/v1/inventories/{$inventoryId}");

        $deleteResponse->assertOk()
            ->assertJsonPath('message', 'Inventory item deleted');

        $this->assertDatabaseMissing('inventory_items', [
            'id' => $inventoryId,
        ]);
    }

    public function test_inventory_listing_is_scoped_to_the_current_company(): void
    {
        [$ownerA, $companyA] = $this->actingAsCompanyOwner('inventory-scope-a');
        [$ownerB, $companyB] = $this->createCompany('inventory-scope-b');

        $visibleItem = InventoryItem::query()->create([
            'company_id' => $companyA->getKey(),
            'code' => 'A-ITEM-1',
            'name' => 'Visible Item',
            'description' => 'Visible company item.',
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'quantity_on_hand' => 4,
            'reorder_point' => 10,
            'item_type' => 'inventory',
            'category' => 'Goods',
        ]);

        InventoryItem::query()->create([
            'company_id' => $companyB->getKey(),
            'code' => 'B-ITEM-1',
            'name' => 'Hidden Item',
            'description' => 'Other company item.',
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'quantity_on_hand' => 1,
            'reorder_point' => 20,
            'item_type' => 'inventory',
            'category' => 'Goods',
        ]);

        Sanctum::actingAs($ownerA, [], 'sanctum');

        $response = $this->getJson('/api/v1/inventories');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', (string) $visibleItem->getKey())
            ->assertJsonPath('data.0.is_low_stock', true)
            ->assertJsonPath('stats.low_stock_count', 1)
            ->assertJsonMissing(['code' => 'B-ITEM-1']);
    }

    public function test_inventory_delete_returns_a_friendly_conflict_when_item_is_used_on_sales_invoices(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('inventory-delete-sales-conflict');

        $item = InventoryItem::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'COF-1001',
            'name' => 'Arabica Coffee',
            'status' => 'active',
            'unit_of_measure' => 'BAG',
            'item_type' => 'inventory',
            'category' => 'Coffee Beans',
        ]);

        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'CUST-1001',
            'name' => 'Brewed Awakening Cafe',
            'status' => 'active',
        ]);

        $invoice = SalesInvoice::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'invoice_number' => 'INV-1001',
            'status' => 'draft',
            'invoice_date' => '2026-03-30',
            'subtotal' => 4500,
        ]);

        SalesInvoiceLine::query()->create([
            'sales_invoice_id' => $invoice->getKey(),
            'company_id' => $company->getKey(),
            'inventory_item_id' => $item->getKey(),
            'line_number' => 1,
            'item_code_snapshot' => 'COF-1001',
            'item_name_snapshot' => 'Arabica Coffee',
            'item_description_snapshot' => 'Premium roasted beans.',
            'unit_of_measure_snapshot' => 'BAG',
            'item_type_snapshot' => 'inventory',
            'quantity' => 3,
            'unit_price' => 1500,
            'line_subtotal' => 4500,
        ]);

        $this->deleteJson("/api/v1/inventories/{$item->getKey()}")
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                "Arabica Coffee can't be deleted because it is already used on one or more sales invoices. Mark it as inactive instead if you no longer want to use it."
            );

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->getKey(),
        ]);
    }

    public function test_inventory_delete_returns_a_friendly_conflict_when_item_has_stock_history(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('inventory-delete-stock-history-conflict');

        $item = InventoryItem::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'COF-2001',
            'name' => 'Arabica Reserve',
            'status' => 'active',
            'unit_of_measure' => 'BAG',
            'item_type' => 'inventory',
            'category' => 'Coffee Beans',
        ]);

        InventoryMovement::query()->create([
            'company_id' => $company->getKey(),
            'inventory_item_id' => $item->getKey(),
            'movement_type' => 'adjustment',
            'direction' => 'in',
            'quantity' => 12,
            'quantity_before' => 0,
            'quantity_after' => 12,
            'occurred_at' => '2026-03-30 09:00:00',
            'notes' => 'Opening stock recorded.',
        ]);

        $this->deleteJson("/api/v1/inventories/{$item->getKey()}")
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                "Arabica Reserve can't be deleted because stock activity has already been recorded for it. Mark it as inactive instead if you no longer want to use it."
            );

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->getKey(),
        ]);
    }

    public function test_inventory_index_reports_backend_low_stock_status_and_counts(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('inventory-low-stock-company');

        $lowStockItem = InventoryItem::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'LOW-ITEM-1',
            'name' => 'Low Stock Item',
            'description' => 'Needs replenishment.',
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'quantity_on_hand' => 5,
            'reorder_point' => 10,
            'item_type' => 'inventory',
            'category' => 'Raw Materials',
        ]);

        $healthyItem = InventoryItem::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'HEALTHY-1',
            'name' => 'Healthy Item',
            'description' => 'Sufficient stock.',
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'quantity_on_hand' => 25,
            'reorder_point' => 10,
            'item_type' => 'inventory',
            'category' => 'Raw Materials',
        ]);

        $inactiveItem = InventoryItem::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'INACTIVE-1',
            'name' => 'Inactive Item',
            'description' => 'Inactive stock master.',
            'status' => 'inactive',
            'unit_of_measure' => 'PCS',
            'quantity_on_hand' => 1,
            'reorder_point' => 10,
            'item_type' => 'inventory',
            'category' => 'Archived',
        ]);

        $serviceItem = InventoryItem::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'SERVICE-1',
            'name' => 'Service Item',
            'description' => 'Not stock tracked.',
            'status' => 'active',
            'unit_of_measure' => 'HRS',
            'quantity_on_hand' => 0,
            'reorder_point' => 10,
            'item_type' => 'service',
            'category' => 'Services',
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->getJson('/api/v1/inventories');

        $response->assertOk()
            ->assertJsonPath('stats.total', 4)
            ->assertJsonPath('stats.inventory_count', 3)
            ->assertJsonPath('stats.service_count', 1)
            ->assertJsonPath('stats.low_stock_count', 1)
            ->assertJsonPath('stats.quantity_on_hand_total', 31)
            ->assertJsonPath('stats.inventory_value_total', 0);

        $itemsById = collect($response->json('data'))->keyBy('id');

        $this->assertTrue($itemsById[(string) $lowStockItem->getKey()]['is_low_stock']);
        $this->assertSame('low_stock', $itemsById[(string) $lowStockItem->getKey()]['stock_health']);

        $this->assertFalse($itemsById[(string) $healthyItem->getKey()]['is_low_stock']);
        $this->assertSame('healthy', $itemsById[(string) $healthyItem->getKey()]['stock_health']);

        $this->assertFalse($itemsById[(string) $inactiveItem->getKey()]['is_low_stock']);
        $this->assertSame('inactive', $itemsById[(string) $inactiveItem->getKey()]['stock_health']);

        $this->assertFalse($itemsById[(string) $serviceItem->getKey()]['is_low_stock']);
        $this->assertSame('not_applicable', $itemsById[(string) $serviceItem->getKey()]['stock_health']);
        $this->assertNull($itemsById[(string) $serviceItem->getKey()]['stock_value']);
    }

    public function test_inventory_index_reports_stock_value_totals_for_inventory_items(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('inventory-value-company');

        InventoryItem::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'INV-VALUED-1',
            'name' => 'Valued Stock 1',
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'quantity_on_hand' => 10,
            'cost_price' => 25.5,
            'item_type' => 'inventory',
        ]);

        InventoryItem::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'INV-VALUED-2',
            'name' => 'Valued Stock 2',
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'quantity_on_hand' => 4,
            'cost_price' => 10,
            'item_type' => 'inventory',
        ]);

        InventoryItem::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'SERVICE-VALUED-1',
            'name' => 'Service Line',
            'status' => 'active',
            'unit_of_measure' => 'HRS',
            'quantity_on_hand' => 0,
            'cost_price' => 999,
            'item_type' => 'service',
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->getJson('/api/v1/inventories');

        $response->assertOk()
            ->assertJsonPath('stats.inventory_value_total', 295);

        $itemsByCode = collect($response->json('data'))->keyBy('code');

        $this->assertSame(255.0, (float) $itemsByCode['INV-VALUED-1']['stock_value']);
        $this->assertSame(40.0, (float) $itemsByCode['INV-VALUED-2']['stock_value']);
        $this->assertNull($itemsByCode['SERVICE-VALUED-1']['stock_value']);
    }

    public function test_inventory_show_returns_not_found_for_other_company_item(): void
    {
        [$ownerA, $companyA] = $this->actingAsCompanyOwner('inventory-show-a');
        [$ownerB, $companyB] = $this->createCompany('inventory-show-b');

        $hiddenItem = InventoryItem::query()->create([
            'company_id' => $companyB->getKey(),
            'code' => 'HIDDEN-1',
            'name' => 'Hidden Item',
            'description' => 'Other company item.',
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'item_type' => 'inventory',
            'category' => 'Goods',
        ]);

        Sanctum::actingAs($ownerA, [], 'sanctum');

        $this->getJson("/api/v1/inventories/{$hiddenItem->getKey()}")
            ->assertStatus(404);
    }

    public function test_inventory_requests_reject_mismatched_company_headers(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('inventory-header-company');
        [$otherOwner, $otherCompany] = $this->createCompany('inventory-header-other');

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $otherCompany->getKey(),
        ])->getJson('/api/v1/inventories');

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Company switching is no longer supported for this account.');
    }

    public function test_inventory_write_routes_require_inventory_write_ability_for_non_admin_members(): void
    {
        [$company, $member] = $this->createCompanyMemberWithRole([
            'inventory' => ['read'],
        ]);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->postJson('/api/v1/inventories', [
            'code' => 'NO-WRITE-1',
            'name' => 'No Write Item',
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'item_type' => 'inventory',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');
    }

    public function test_inventory_item_mutations_are_audited_with_company_context(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('inventory-audit-company');

        $createResponse = $this->postJson('/api/v1/inventories', [
            'code' => 'AUD-1001',
            'name' => 'Audit Item',
            'description' => 'Audit create',
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'item_type' => 'inventory',
            'category' => 'Audited',
        ]);

        $inventoryId = (string) $createResponse->json('data.id');

        $this->putJson("/api/v1/inventories/{$inventoryId}", [
            'description' => 'Audit update',
            'category' => 'Updated Audit',
        ])->assertOk();

        $this->patchJson("/api/v1/inventories/{$inventoryId}/status", [
            'status' => 'inactive',
        ])->assertOk();

        $this->deleteJson("/api/v1/inventories/{$inventoryId}")
            ->assertOk();

        $createdAudit = AuditLog::query()
            ->where('action', 'inventory_item.created')
            ->where('tenant_id', $company->getKey())
            ->firstOrFail();

        $updatedAudit = AuditLog::query()
            ->where('action', 'inventory_item.updated')
            ->where('tenant_id', $company->getKey())
            ->firstOrFail();

        $deactivatedAudit = AuditLog::query()
            ->where('action', 'inventory_item.deactivated')
            ->where('tenant_id', $company->getKey())
            ->firstOrFail();

        $deletedAudit = AuditLog::query()
            ->where('action', 'inventory_item.deleted')
            ->where('tenant_id', $company->getKey())
            ->firstOrFail();

        $this->assertSame((string) $company->getKey(), (string) $createdAudit->tenant_id);
        $this->assertSame((string) $company->getKey(), $createdAudit->meta['company_id'] ?? null);
        $this->assertSame('AUD-1001', $createdAudit->after['code'] ?? null);

        $this->assertSame((string) $company->getKey(), $updatedAudit->meta['company_id'] ?? null);
        $this->assertSame('Updated Audit', $updatedAudit->after['category'] ?? null);

        $this->assertSame((string) $company->getKey(), $deactivatedAudit->meta['company_id'] ?? null);
        $this->assertSame('inactive', $deactivatedAudit->after['status'] ?? null);

        $this->assertSame((string) $company->getKey(), $deletedAudit->meta['company_id'] ?? null);
        $this->assertSame($inventoryId, (string) $deletedAudit->auditable_id);
    }

    /**
     * @param array<string, array<int, string>> $modulePermissions
     * @return array{0: Tenant, 1: User}
     */
    private function createCompanyMemberWithRole(array $modulePermissions): array
    {
        [$owner, $company] = $this->createCompany('inventory-member-company');
        $member = User::factory()->create([
            'company_id' => $company->getKey(),
        ]);

        $membership = TenantMembership::query()->create([
            'tenant_id' => $company->getKey(),
            'user_id' => $member->getKey(),
            'status' => 'active',
            'invited_by_user_id' => $owner->getKey(),
            'joined_at' => now(),
        ]);

        $role = Role::query()->create([
            'tenant_id' => $company->getKey(),
            'system_generated' => false,
            'key' => 'inventory-clerk',
            'name' => 'Inventory Clerk',
            'department' => 'Operations',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'department',
            'module_permissions' => $modulePermissions,
            'description' => 'Inventory role.',
        ]);

        $membership->roles()->attach($role->getKey());

        return [$company, $member];
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function actingAsCompanyOwner(string $slug): array
    {
        [$owner, $company] = $this->createCompany($slug);

        Sanctum::actingAs($owner, [], 'sanctum');

        return [$owner, $company];
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function createCompany(string $slug): array
    {
        $owner = User::factory()->create([
            'company_id' => null,
        ]);

        $company = Tenant::query()->create([
            'name' => 'Inventory '.strtoupper($slug),
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->getKey(),
        ]);

        $owner->forceFill(['company_id' => $company->getKey()])->save();

        return [$owner, $company];
    }
}

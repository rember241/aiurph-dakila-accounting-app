<?php

namespace Modules\Customers\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Customers\Models\Customer;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/customers')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_customer_index_requires_customer_read_ability_for_non_admin_members(): void
    {
        [$company, $member] = $this->createCompanyMemberWithRole([
            'sales' => ['read'],
        ]);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->getJson('/api/v1/customers');

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');
    }

    public function test_customer_owner_can_create_list_show_update_transition_and_delete_customers(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('customer-owner-company');

        $createResponse = $this->postJson('/api/v1/customers', [
            'name' => 'Acme Distribution Corp',
            'customer_group' => 'commercial',
            'email' => 'billing@acme.test',
            'phone' => '+63 917 555 1001',
            'address' => '123 Trade Avenue, Manila',
            'status' => 'active',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('message', 'Customer created')
            ->assertJsonPath('data.company_id', (string) $company->getKey())
            ->assertJsonPath('data.code', 'CUST-0001')
            ->assertJsonPath('data.name', 'Acme Distribution Corp')
            ->assertJsonPath('data.customer_group', 'commercial')
            ->assertJsonPath('data.email', 'billing@acme.test')
            ->assertJsonPath('data.phone', '+63 917 555 1001')
            ->assertJsonPath('data.address', '123 Trade Avenue, Manila')
            ->assertJsonPath('data.status', 'active');

        $customerId = (string) $createResponse->json('data.id');

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'company_id' => $company->getKey(),
            'code' => 'CUST-0001',
            'customer_group' => 'commercial',
            'email' => 'billing@acme.test',
        ]);

        $indexResponse = $this->getJson('/api/v1/customers?q=acme');

        $indexResponse->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.active', 1)
            ->assertJsonPath('stats.inactive', 0)
            ->assertJsonPath('data.0.id', $customerId);

        $showResponse = $this->getJson("/api/v1/customers/{$customerId}");

        $showResponse->assertOk()
            ->assertJsonPath('data.id', $customerId)
            ->assertJsonPath('data.customer_group', 'commercial')
            ->assertJsonPath('data.name', 'Acme Distribution Corp');

        $updateResponse = $this->putJson("/api/v1/customers/{$customerId}", [
            'name' => 'Acme Distribution Corporation',
            'customer_group' => 'government',
            'email' => 'collections@acme.test',
            'phone' => '+63 917 555 2002',
            'address' => '789 Updated Avenue, Cebu',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('message', 'Customer updated')
            ->assertJsonPath('data.name', 'Acme Distribution Corporation')
            ->assertJsonPath('data.customer_group', 'government')
            ->assertJsonPath('data.email', 'collections@acme.test')
            ->assertJsonPath('data.phone', '+63 917 555 2002')
            ->assertJsonPath('data.address', '789 Updated Avenue, Cebu');

        $transitionResponse = $this->patchJson("/api/v1/customers/{$customerId}/status", [
            'status' => 'inactive',
        ]);

        $transitionResponse->assertOk()
            ->assertJsonPath('message', 'Customer status updated')
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.is_active', false);

        $deleteResponse = $this->deleteJson("/api/v1/customers/{$customerId}");

        $deleteResponse->assertOk()
            ->assertJsonPath('message', 'Customer deleted');

        $this->assertDatabaseMissing('customers', [
            'id' => $customerId,
        ]);
    }

    public function test_customer_listing_is_scoped_to_the_current_company(): void
    {
        [$ownerA, $companyA] = $this->actingAsCompanyOwner('customer-scope-a');
        [$ownerB, $companyB] = $this->createCompany('customer-scope-b');

        $visibleCustomer = Customer::query()->create([
            'company_id' => $companyA->getKey(),
            'code' => 'VISIBLE-1',
            'name' => 'Visible Customer',
            'email' => 'visible@example.test',
            'status' => 'active',
        ]);

        Customer::query()->create([
            'company_id' => $companyB->getKey(),
            'code' => 'HIDDEN-1',
            'name' => 'Hidden Customer',
            'email' => 'hidden@example.test',
            'status' => 'active',
        ]);

        Sanctum::actingAs($ownerA, [], 'sanctum');

        $response = $this->getJson('/api/v1/customers');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', (string) $visibleCustomer->getKey())
            ->assertJsonPath('data.0.customer_group', null)
            ->assertJsonMissing(['code' => 'HIDDEN-1']);
    }

    public function test_customer_show_returns_not_found_for_other_company_customer(): void
    {
        [$ownerA, $companyA] = $this->actingAsCompanyOwner('customer-show-a');
        [$ownerB, $companyB] = $this->createCompany('customer-show-b');

        $hiddenCustomer = Customer::query()->create([
            'company_id' => $companyB->getKey(),
            'code' => 'HIDDEN-1',
            'name' => 'Hidden Customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($ownerA, [], 'sanctum');

        $this->getJson("/api/v1/customers/{$hiddenCustomer->getKey()}")
            ->assertStatus(404);
    }

    public function test_customer_requests_reject_mismatched_company_headers(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('customer-header-company');
        [$otherOwner, $otherCompany] = $this->createCompany('customer-header-other');

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $otherCompany->getKey(),
        ])->getJson('/api/v1/customers');

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Company switching is no longer supported for this account.');
    }

    public function test_customer_write_routes_require_customer_write_ability_for_non_admin_members(): void
    {
        [$company, $member] = $this->createCompanyMemberWithRole([
            'customers' => ['read'],
        ]);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'No Write Customer',
            'status' => 'active',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');
    }

    public function test_customer_create_rejects_invalid_payloads(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('customer-invalid-payload-company');

        $response = $this->postJson('/api/v1/customers', [
            'code' => 'invalid code',
            'name' => '',
            'customer_group' => 'enterprise',
            'email' => 'not-an-email',
            'phone' => str_repeat('1', 41),
            'address' => str_repeat('a', 501),
            'status' => 'archived',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name', 'customer_group', 'email', 'phone', 'address', 'status']);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_update_rejects_blank_name(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('customer-invalid-update-company');

        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'VALID-1001',
            'name' => 'Valid Customer',
            'status' => 'active',
        ]);

        $response = $this->putJson("/api/v1/customers/{$customer->getKey()}", [
            'name' => '   ',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->getKey(),
            'name' => 'Valid Customer',
        ]);
    }

    public function test_customer_mutations_are_audited_with_company_context(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('customer-audit-company');

        $createResponse = $this->postJson('/api/v1/customers', [
            'name' => 'Audit Customer',
            'customer_group' => 'individual',
            'email' => 'audit@example.test',
            'status' => 'active',
        ]);

        $customerId = (string) $createResponse->json('data.id');

        $this->putJson("/api/v1/customers/{$customerId}", [
            'customer_group' => 'commercial',
            'address' => 'Updated audit address',
            'phone' => '+63 917 555 9999',
        ])->assertOk();

        $this->patchJson("/api/v1/customers/{$customerId}/status", [
            'status' => 'inactive',
        ])->assertOk();

        $this->deleteJson("/api/v1/customers/{$customerId}")
            ->assertOk();

        $createdAudit = AuditLog::query()
            ->where('action', 'customer.created')
            ->where('tenant_id', $company->getKey())
            ->firstOrFail();

        $updatedAudit = AuditLog::query()
            ->where('action', 'customer.updated')
            ->where('tenant_id', $company->getKey())
            ->firstOrFail();

        $deactivatedAudit = AuditLog::query()
            ->where('action', 'customer.deactivated')
            ->where('tenant_id', $company->getKey())
            ->firstOrFail();

        $deletedAudit = AuditLog::query()
            ->where('action', 'customer.deleted')
            ->where('tenant_id', $company->getKey())
            ->firstOrFail();

        $this->assertSame((string) $company->getKey(), (string) $createdAudit->tenant_id);
        $this->assertSame((string) $company->getKey(), $createdAudit->meta['company_id'] ?? null);
        $this->assertSame('CUST-0001', $createdAudit->after['code'] ?? null);
        $this->assertSame('individual', $createdAudit->after['customer_group'] ?? null);

        $this->assertSame((string) $company->getKey(), $updatedAudit->meta['company_id'] ?? null);
        $this->assertSame('Updated audit address', $updatedAudit->after['address'] ?? null);
        $this->assertSame('commercial', $updatedAudit->after['customer_group'] ?? null);

        $this->assertSame((string) $company->getKey(), $deactivatedAudit->meta['company_id'] ?? null);
        $this->assertSame('inactive', $deactivatedAudit->after['status'] ?? null);

        $this->assertSame((string) $company->getKey(), $deletedAudit->meta['company_id'] ?? null);
        $this->assertSame($customerId, (string) $deletedAudit->auditable_id);
    }

    public function test_customer_create_auto_increments_code_from_existing_company_sequence(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('customer-sequence-company');

        Customer::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'CUST-0007',
            'name' => 'Existing Seven',
            'status' => 'active',
        ]);

        Customer::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'LEGACY-CUSTOM',
            'name' => 'Legacy Custom',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Next Sequence Customer',
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'CUST-0008');
    }

    /**
     * @param array<string, array<int, string>> $modulePermissions
     * @return array{0: Tenant, 1: User}
     */
    private function createCompanyMemberWithRole(array $modulePermissions): array
    {
        [$owner, $company] = $this->createCompany('customer-member-company');
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
            'key' => 'customer-clerk',
            'name' => 'Customer Clerk',
            'department' => 'Sales',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'department',
            'module_permissions' => $modulePermissions,
            'description' => 'Customer role.',
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
            'name' => 'Customer '.strtoupper($slug),
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->getKey(),
        ]);

        $owner->forceFill(['company_id' => $company->getKey()])->save();

        return [$owner, $company];
    }
}

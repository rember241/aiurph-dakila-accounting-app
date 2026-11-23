<?php

namespace Modules\Users\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Laravel\Sanctum\Sanctum;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_tenant_with_owner_membership_and_tenant_scoped_admin_role(): void
    {
        $user = User::factory()->create();

        Role::create([
            'key' => 'admin',
            'name' => 'Admin',
            'department' => 'Administration',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'global',
            'module_permissions' => [
                'accounting' => ['read', 'write'],
                'roles' => ['read', 'write'],
                'users' => ['read', 'write'],
            ],
            'description' => 'Global admin role template.',
        ]);
        Role::create([
            'key' => 'accountant',
            'name' => 'Accountant',
            'department' => 'Accounting',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'global',
            'module_permissions' => [
                'accounting' => ['read', 'write'],
                'journal' => ['read', 'write'],
            ],
            'description' => 'Global accountant role template.',
        ]);
        Role::create([
            'key' => 'viewer',
            'name' => 'Viewer',
            'department' => null,
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'global',
            'module_permissions' => [
                'accounting' => ['read'],
            ],
            'description' => 'Global viewer role template.',
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Acme Trading Co',
            'timezone' => 'Asia/Manila',
        ]);

        $response->assertCreated()
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('X-Legacy-Contract', 'tenant')
            ->assertJsonPath('data.name', 'Acme Trading Co')
            ->assertJsonPath('data.slug', 'acme-trading-co')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.timezone', 'Asia/Manila')
            ->assertJsonPath('data.owner.id', (string)$user->id)
            ->assertJsonPath('data.owner_membership.user_id', (string)$user->id)
            ->assertJsonPath('data.owner_membership.roles.0.key', 'admin');

        $tenant = Tenant::query()->where('slug', 'acme-trading-co')->firstOrFail();
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $tenantRoles = Role::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('key')
            ->get()
            ->keyBy('key');

        $this->assertCount(3, $tenantRoles);
        $this->assertTrue($tenantRoles->has('admin'));
        $this->assertTrue($tenantRoles->has('accountant'));
        $this->assertTrue($tenantRoles->has('viewer'));
        $this->assertTrue((bool)$tenantRoles['admin']->system_generated);
        $this->assertTrue((bool)$tenantRoles['accountant']->system_generated);
        $this->assertTrue((bool)$tenantRoles['viewer']->system_generated);
        $this->assertTrue($membership->roles()->whereKey($tenantRoles['admin']->id)->exists());
        $this->assertFalse($membership->roles()->whereKey($tenantRoles['accountant']->id)->exists());
        $this->assertFalse($membership->roles()->whereKey($tenantRoles['viewer']->id)->exists());

        $expectedYear = CarbonImmutable::now('Asia/Manila')->year;
        $fiscalYear = FiscalYear::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', sprintf('FY%d', $expectedYear))
            ->firstOrFail();

        $this->assertSame((string)$tenant->id, (string)$fiscalYear->tenant_id);
        $this->assertSame(
            12,
            AccountingPeriod::query()
                ->where('tenant_id', $tenant->id)
                ->where('fiscal_year_id', $fiscalYear->getKey())
                ->count()
        );
        $this->assertDatabaseHas('org_units', [
            'company_id' => $tenant->id,
            'name' => 'Head Office',
            'type' => 'head_office',
            'is_root' => true,
            'status' => 'active',
        ]);
        $rootOrgUnit = OrgUnit::query()
            ->where('company_id', $tenant->id)
            ->where('is_root', true)
            ->firstOrFail();
        $user->refresh();

        $this->assertDatabaseMissing('tenant_databases', [
            'tenant_id' => $tenant->id,
        ]);
        $this->assertSame((string) $tenant->id, (string) $user->company_id);
        $this->assertSame((string) $rootOrgUnit->getKey(), (string) $user->primary_org_unit_id);
        $this->assertDatabaseHas('user_org_unit_assignments', [
            'user_id' => $user->id,
            'company_id' => $tenant->id,
            'org_unit_id' => $rootOrgUnit->getKey(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant.created',
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant_membership.created',
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role.created',
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant_membership.role_assigned',
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'auditable_id' => $membership->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'fiscal_year.created',
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'auditable_id' => $fiscalYear->getKey(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org_unit.created',
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'auditable_id' => $rootOrgUnit->getKey(),
        ]);
        $this->assertSame(
            3,
            DB::table('audit_logs')
                ->where('action', 'role.created')
                ->where('tenant_id', $tenant->id)
                ->count()
        );

        $tenantCreatedAudit = AuditLog::query()
            ->where('action', 'tenant.created')
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
        $ownerRoleAssignedAudit = AuditLog::query()
            ->where('action', 'tenant_membership.role_assigned')
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $this->assertSame((string)$tenant->id, $tenantCreatedAudit->after['tenant_id'] ?? null);
        $this->assertFalse($tenantCreatedAudit->after['tenant_database_provisioning_requested'] ?? true);
        $this->assertSame((string)$membership->id, $tenantCreatedAudit->after['owner_membership_id'] ?? null);
        $this->assertSame('admin', $tenantCreatedAudit->after['owner_role_key'] ?? null);
        $this->assertSame((string)$tenant->id, $ownerRoleAssignedAudit->after['tenant_id'] ?? null);
        $this->assertSame((string)$membership->id, $ownerRoleAssignedAudit->after['membership_id'] ?? null);
        $this->assertSame((string)$user->id, $ownerRoleAssignedAudit->after['user_id'] ?? null);
        $this->assertSame('admin', $ownerRoleAssignedAudit->after['role_key'] ?? null);
        $this->assertSame('tenant_owner_bootstrap', $ownerRoleAssignedAudit->after['assignment_source'] ?? null);
    }

    public function test_guest_cannot_create_tenant(): void
    {
        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Guest Company',
        ]);

        $response->assertUnauthorized();
    }

    public function test_user_cannot_create_a_tenant_after_initial_company_setup_is_completed(): void
    {
        $user = User::factory()->create();

        Tenant::create([
            'name' => 'Existing Company',
            'slug' => 'existing-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Second Company',
            'timezone' => 'Asia/Manila',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);

        $this->assertSame(1, Tenant::query()->where('owner_user_id', $user->id)->count());
    }

    public function test_slug_must_be_unique_when_provided(): void
    {
        $user = User::factory()->create();

        Tenant::create([
            'name' => 'Existing Tenant',
            'slug' => 'existing-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Another Tenant',
            'slug' => 'existing-tenant',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_index_returns_only_the_authenticated_users_current_company(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedTenant = Tenant::create([
            'name' => 'Owned Tenant',
            'slug' => 'owned-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        $memberTenant = Tenant::create([
            'name' => 'Member Tenant',
            'slug' => 'member-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $otherUser->id,
        ]);

        TenantMembership::create([
            'tenant_id' => $memberTenant->id,
            'user_id' => $user->id,
            'status' => 'active',
            'invited_by_user_id' => $otherUser->id,
            'joined_at' => now(),
        ]);

        Tenant::create([
            'name' => 'Hidden Tenant',
            'slug' => 'hidden-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $otherUser->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson('/api/v1/tenants');

        $response->assertOk()
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('X-Replacement-Path', '/api/v1/companies')
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data');

        $tenantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains((string)$ownedTenant->id, $tenantIds);
        $this->assertNotContains((string)$memberTenant->id, $tenantIds);
    }

    public function test_show_returns_visible_tenant_for_membership_user(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Visible Tenant',
            'slug' => 'visible-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', (string)$tenant->id)
            ->assertJsonPath('data.slug', 'visible-tenant');
    }

    public function test_show_returns_not_found_for_invisible_tenant(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Private Tenant',
            'slug' => 'private-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}");

        $response->assertNotFound();
    }
}

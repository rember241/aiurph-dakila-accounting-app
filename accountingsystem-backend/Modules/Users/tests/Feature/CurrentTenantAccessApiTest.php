<?php

namespace Modules\Users\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class CurrentTenantAccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_even_owners_cannot_access_disabled_modules_but_can_open_accounting(): void
    {
        $owner = User::factory()->create();
        Tenant::create([
            'name' => 'Accounting Only Company',
            'slug' => 'accounting-only-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);
        Sanctum::actingAs($owner, [], 'sanctum');

        foreach (['users', 'org-units'] as $path) {
            $this->getJson('/api/v1/'.$path)->assertForbidden();
        }
        foreach (['sales-invoices', 'customers', 'inventories', 'expenses', 'settings/user', 'cheque-disbursements'] as $path) {
            $this->getJson('/api/v1/'.$path)->assertNotFound();
        }
        $this->getJson('/api/v1/accounts')->assertOk();
        $this->getJson('/api/v1/roles')->assertOk();
        $this->getJson('/api/v1/settings/company')->assertOk();
        $this->putJson('/api/v1/settings/company', [])->assertForbidden();
        $this->getJson('/api/v1/accounting/dashboard?startDate=2026-10-01&date=2026-10-23')->assertOk()->assertJsonStructure(['income' => ['totalRevenue', 'totalExpense', 'netTotal'], 'balance' => ['totalAssets', 'totalLiabilities', 'totalEquities']]);
    }

    public function test_owner_receives_full_access_snapshot(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Owner Access Tenant',
            'slug' => 'owner-access-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->getJson('/api/v1/me/access');

        $response->assertOk()
            ->assertJsonPath('data.company_id', (string) $tenant->id)
            ->assertJsonPath('data.tenant_id', (string) $tenant->id)
            ->assertJsonPath('data.full_access', true)
            ->assertJsonPath('data.disabled_modules', ['customers', 'sales', 'expenses', 'inventory', 'users', 'dashboard_reports', 'org_units', 'settings'])
            ->assertJsonPath('data.abilities', []);
    }

    public function test_membership_roles_are_flattened_into_effective_abilities(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Membership Access Tenant',
            'slug' => 'membership-access-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        $salesRole = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => false,
            'key' => 'sales-clerk',
            'name' => 'Sales Clerk',
            'department' => 'Sales',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'sales' => ['read', 'write'],
                'customers' => ['read'],
            ],
            'description' => 'Sales role.',
        ]);

        $periodRole = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => false,
            'key' => 'period-viewer',
            'name' => 'Period Viewer',
            'department' => 'Finance',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'department',
            'module_permissions' => [
                'period' => ['read'],
            ],
            'description' => 'Period role.',
        ]);

        $membership->roles()->attach([$salesRole->id, $periodRole->id]);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->getJson('/api/v1/me/access');

        $response->assertOk()
            ->assertJsonPath('data.company_id', (string) $tenant->id)
            ->assertJsonPath('data.tenant_id', (string) $tenant->id)
            ->assertJsonPath('data.full_access', false)
            ->assertJsonPath('data.abilities', [
                'period.read',
            ]);
        $response->assertJsonPath('data.disabled_modules', ['customers', 'sales', 'expenses', 'inventory', 'users', 'dashboard_reports', 'org_units', 'settings']);
    }

    public function test_users_and_roles_permissions_do_not_implicitly_grant_company_structure_abilities(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Legacy Users Security Tenant',
            'slug' => 'legacy-users-security-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => false,
            'key' => 'ops-admin',
            'name' => 'Operations Admin',
            'department' => 'Operations',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'users' => ['read', 'write'],
                'roles' => ['read', 'write'],
            ],
            'description' => 'Legacy users-security role.',
        ]);

        $membership->roles()->attach($role->id);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->getJson('/api/v1/me/access');

        $response->assertOk()
            ->assertJsonPath('data.full_access', false)
            ->assertJsonPath('data.abilities', ['roles.read', 'roles.write']);
    }

    public function test_granular_module_permissions_preserve_exact_verbs_without_promoting_them_to_write(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Granular Ability Tenant',
            'slug' => 'granular-ability-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => false,
            'key' => 'journal-preparer',
            'name' => 'Journal Preparer',
            'department' => 'Finance',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'journal' => ['read', 'create'],
                'inventory' => ['read'],
            ],
            'description' => 'Granular permission role.',
        ]);

        $membership->roles()->attach($role->id);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->getJson('/api/v1/me/access');

        $response->assertOk()
            ->assertJsonPath('data.full_access', false)
            ->assertJsonPath('data.abilities', [
                'journal.create',
                'journal.read',
            ]);
    }

    public function test_create_and_delete_permissions_do_not_imply_write_ability(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Separated Crud Ability Tenant',
            'slug' => 'separated-crud-ability-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => false,
            'key' => 'role-curator',
            'name' => 'Role Curator',
            'department' => 'Administration',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'roles' => ['create', 'delete'],
            ],
            'description' => 'Can create and delete roles without edit rights.',
        ]);

        $membership->roles()->attach($role->id);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->getJson('/api/v1/me/access');

        $response->assertOk()
            ->assertJsonPath('data.full_access', false)
            ->assertJsonPath('data.abilities', ['roles.create', 'roles.delete']);
    }

    public function test_tenant_admin_membership_honors_explicit_module_permissions(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Admin Access Tenant',
            'slug' => 'admin-access-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        $adminRole = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => true,
            'key' => 'admin',
            'name' => 'Admin',
            'department' => 'Administration',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'global',
            'module_permissions' => [
                'accounting' => ['read', 'write'],
            ],
            'description' => 'Tenant admin role.',
        ]);

        $membership->roles()->attach($adminRole->id);

        Sanctum::actingAs($admin, [], 'sanctum');

        $response = $this->getJson('/api/v1/me/access');

        $response->assertOk()
            ->assertJsonPath('data.company_id', (string) $tenant->id)
            ->assertJsonPath('data.tenant_id', (string) $tenant->id)
            ->assertJsonPath('data.full_access', false)
            ->assertJsonPath('data.abilities', [
                'accounting.read',
                'accounting.write',
            ]);
    }
}

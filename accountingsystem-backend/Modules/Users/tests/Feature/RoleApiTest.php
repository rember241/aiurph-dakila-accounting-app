<?php

namespace Modules\Users\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class RoleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_with_tenant_role_can_list_roles_without_token_abilities(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Role Access Tenant',
            'slug' => 'role-access-tenant',
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

        $adminRole = Role::create([
            'tenant_id' => $tenant->id,
            'key' => 'admin',
            'name' => 'Admin',
            'department' => 'Administration',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'global',
            'module_permissions' => [
                'roles' => ['read', 'write'],
            ],
            'description' => 'Tenant admin role.',
        ]);

        $membership->roles()->attach($adminRole->id);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->getJson('/api/v1/roles');

        $response->assertOk()
            ->assertJsonPath('data.0.id', (string) $adminRole->id);
    }

    public function test_read_only_members_can_view_roles_but_receive_no_edit_delete_or_deactivate_affordances(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Role Viewer Tenant',
            'slug' => 'role-viewer-tenant',
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

        $viewerRole = Role::create([
            'tenant_id' => $tenant->id,
            'key' => 'role-viewer',
            'name' => 'Role Viewer',
            'department' => 'Audit',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'department',
            'module_permissions' => [
                'roles' => ['read'],
            ],
            'description' => 'Can only view roles.',
        ]);

        $targetRole = Role::create([
            'tenant_id' => $tenant->id,
            'key' => 'finance-manager',
            'name' => 'Finance Manager',
            'department' => 'Finance',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'roles' => ['read', 'write'],
            ],
            'description' => 'Target role for visibility assertions.',
        ]);

        $membership->roles()->attach($viewerRole->id);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->getJson('/api/v1/roles');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => (string) $targetRole->id,
                'can_edit' => false,
                'can_delete' => false,
                'can_deactivate' => false,
            ]);
    }

    public function test_roles_list_stats_and_detail_are_scoped_to_the_active_tenant(): void
    {
        $owner = User::factory()->create();

        $tenantA = Tenant::create([
            'name' => 'Tenant Alpha',
            'slug' => 'tenant-alpha',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $tenantB = Tenant::create([
            'name' => 'Tenant Beta',
            'slug' => 'tenant-beta',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $alphaRole = Role::create([
            'tenant_id' => $tenantA->id,
            'key' => 'finance-manager',
            'name' => 'Finance Manager',
            'department' => 'Finance',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'roles' => ['read', 'write'],
            ],
            'description' => 'Finance manager role.',
        ]);

        $betaRole = Role::create([
            'tenant_id' => $tenantB->id,
            'key' => 'viewer',
            'name' => 'Viewer',
            'department' => null,
            'status' => 'inactive',
            'access_level' => 'read',
            'permission_scope' => 'global',
            'module_permissions' => [
                'roles' => ['read'],
            ],
            'description' => 'Viewer role.',
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $listResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenantA->id,
        ])->getJson('/api/v1/roles');

        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $alphaRole->id)
            ->assertJsonPath('data.0.tenant_id', (string) $tenantA->id);

        $statsResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenantA->id,
        ])->getJson('/api/v1/roles/stats');

        $statsResponse->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.active', 1)
            ->assertJsonPath('data.inactive', 0);

        $hiddenDetailResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenantA->id,
        ])->getJson("/api/v1/roles/{$betaRole->id}");

        $hiddenDetailResponse->assertNotFound()
            ->assertJsonPath('message', 'Role not found.');
    }

    public function test_role_create_and_detail_use_the_active_tenant_and_membership_counts(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $otherOwner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Scoped Role Tenant',
            'slug' => 'scoped-role-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $otherOwner->id,
        ]);

        $financeDepartment = OrgUnit::create([
            'company_id' => $tenant->id,
            'parent_org_unit_id' => null,
            'type' => 'department',
            'name' => 'Finance Department',
            'status' => 'active',
            'is_root' => false,
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $createResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/roles', [
            'tenant_id' => (string) $otherTenant->id,
            'key' => 'accountant',
            'name' => 'Accountant',
            'org_unit_id' => (string) $financeDepartment->getKey(),
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'roles' => ['read'],
                'accounting' => ['read', 'write'],
            ],
            'description' => 'Accounting role.',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.tenant_id', (string) $tenant->id)
            ->assertJsonPath('data.system_generated', false)
            ->assertJsonPath('data.role_type', 'custom')
            ->assertJsonPath('data.key', 'accountant')
            ->assertJsonPath('data.org_unit_id', (string) $financeDepartment->getKey())
            ->assertJsonPath('data.org_unit.id', (string) $financeDepartment->getKey())
            ->assertJsonPath('data.org_unit.name', 'Finance Department');

        $roleId = (string) $createResponse->json('data.id');

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);
        $membership->roles()->attach($roleId);

        $detailResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->getJson("/api/v1/roles/{$roleId}");

        $detailResponse->assertOk()
            ->assertJsonPath('data.tenant_id', (string) $tenant->id)
            ->assertJsonPath('data.system_generated', false)
            ->assertJsonPath('data.role_type', 'custom')
            ->assertJsonPath('data.org_unit.id', (string) $financeDepartment->getKey())
            ->assertJsonPath('data.members_count', 1);
    }

    public function test_read_access_level_rejects_write_like_module_permissions(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Read Role Guard Tenant',
            'slug' => 'read-role-guard-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/roles', [
            'key' => 'read-reviewer',
            'name' => 'Read Reviewer',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'department',
            'module_permissions' => [
                'accounting' => ['read', 'write'],
            ],
            'description' => 'Attempted over-grant for a read role.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_permissions']);

        $this->assertDatabaseMissing('roles', [
            'tenant_id' => $tenant->id,
            'key' => 'read-reviewer',
        ]);
    }

    public function test_role_create_rejects_unknown_module_keys(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Unknown Module Tenant',
            'slug' => 'unknown-module-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/roles', [
            'key' => 'bad-module-role',
            'name' => 'Bad Module Role',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'role_management' => ['read'],
            ],
            'description' => 'Should fail validation.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_permissions']);
    }

    public function test_downgrading_access_level_to_read_strips_write_like_module_permissions(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Access Downgrade Tenant',
            'slug' => 'access-downgrade-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'key' => 'ops-editor',
            'name' => 'Operations Editor',
            'department' => 'Operations',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'inventory' => ['read', 'write', 'delete'],
                'sales' => ['read', 'create'],
            ],
            'description' => 'Write-enabled role.',
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/roles/{$role->id}", [
            'access_level' => 'read',
            'description' => 'Downgraded to read-only.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.access_level', 'read')
            ->assertJsonMissingPath('data.module_permissions.inventory')
            ->assertJsonMissingPath('data.module_permissions.sales');

        $role->refresh();

        $this->assertSame([
            'inventory' => ['read'],
            'sales' => ['read'],
        ], $role->module_permissions);
    }

    public function test_only_admin_role_is_protected_from_deletion(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Delete Guard Tenant',
            'slug' => 'delete-guard-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
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
                'roles' => ['read', 'write'],
            ],
            'description' => 'System admin role.',
        ]);

        $viewerRole = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => true,
            'key' => 'viewer',
            'name' => 'Viewer',
            'department' => null,
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'global',
            'module_permissions' => [
                'roles' => ['read'],
            ],
            'description' => 'System viewer role.',
        ]);

        $accountantRole = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => true,
            'key' => 'accountant',
            'name' => 'Accountant',
            'department' => 'Accounting',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'global',
            'module_permissions' => [
                'roles' => ['read', 'write'],
            ],
            'description' => 'System accountant role.',
        ]);

        $customRole = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => false,
            'key' => 'custom-reviewer',
            'name' => 'Custom Reviewer',
            'department' => 'Finance',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'department',
            'module_permissions' => [
                'roles' => ['read', 'write'],
            ],
            'description' => 'Custom reviewer role.',
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $adminDeleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/roles/{$adminRole->id}");

        $adminDeleteResponse->assertStatus(403)
            ->assertJsonPath('message', 'Admin role cannot be deleted.');

        $viewerDeleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/roles/{$viewerRole->id}");

        $viewerDeleteResponse->assertOk()
            ->assertJsonPath('message', 'Role deleted');

        $accountantDeleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/roles/{$accountantRole->id}");

        $accountantDeleteResponse->assertOk()
            ->assertJsonPath('message', 'Role deleted');

        $customDeleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/roles/{$customRole->id}");

        $customDeleteResponse->assertOk()
            ->assertJsonPath('message', 'Role deleted');

        $this->assertDatabaseHas('roles', [
            'id' => $adminRole->id,
        ]);
        $this->assertDatabaseMissing('roles', [
            'id' => $viewerRole->id,
        ]);
        $this->assertDatabaseMissing('roles', [
            'id' => $accountantRole->id,
        ]);
        $this->assertDatabaseMissing('roles', [
            'id' => $customRole->id,
        ]);
    }

    public function test_roles_with_assigned_members_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Role Member Guard Tenant',
            'slug' => 'role-member-guard-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => true,
            'key' => 'viewer',
            'name' => 'Viewer',
            'department' => null,
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'global',
            'module_permissions' => [
                'roles' => ['read'],
            ],
            'description' => 'System viewer role.',
        ]);

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        $membership->roles()->attach($role->id);

        Sanctum::actingAs($owner, [], 'sanctum');

        $deleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/roles/{$role->id}");

        $deleteResponse->assertStatus(409)
            ->assertJsonPath('message', 'Role has assigned users and cannot be deleted.');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_roles_can_be_filtered_by_unassigned_department(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Department Filter Tenant',
            'slug' => 'department-filter-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $unassignedRole = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => false,
            'key' => 'viewer',
            'name' => 'Viewer',
            'department' => null,
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'global',
            'module_permissions' => [
                'roles' => ['read'],
            ],
            'description' => 'Unassigned role.',
        ]);

        Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => false,
            'key' => 'finance-manager',
            'name' => 'Finance Manager',
            'department' => 'Finance',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'roles' => ['read', 'write'],
            ],
            'description' => 'Finance role.',
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->getJson('/api/v1/roles?department=__unassigned__');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $unassignedRole->id)
            ->assertJsonPath('data.0.department', null);
    }

    public function test_deactivating_a_role_suspends_assigned_members_and_reactivation_restores_their_previous_membership_states(): void
    {
        $owner = User::factory()->create();
        $activeMember = User::factory()->create();
        $inactiveMember = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Role Status Cascade Tenant',
            'slug' => 'role-status-cascade-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => true,
            'key' => 'viewer',
            'name' => 'Viewer',
            'department' => null,
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'global',
            'module_permissions' => [
                'roles' => ['read'],
            ],
            'description' => 'System viewer role.',
        ]);

        $activeMembership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $activeMember->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);
        $activeMembership->roles()->attach($role->id);

        $inactiveMembership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $inactiveMember->id,
            'status' => 'inactive',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);
        $inactiveMembership->roles()->attach($role->id);

        $activeMember->createToken('web');

        Sanctum::actingAs($owner, [], 'sanctum');

        $deactivateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->patchJson("/api/v1/roles/{$role->id}/status", [
            'status' => 'inactive',
        ]);

        $deactivateResponse->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('tenant_memberships', [
            'id' => $activeMembership->id,
            'status' => 'inactive',
            'status_before_role_suspension' => 'active',
        ]);
        $this->assertDatabaseHas('tenant_memberships', [
            'id' => $inactiveMembership->id,
            'status' => 'inactive',
            'status_before_role_suspension' => 'inactive',
        ]);
        $this->assertDatabaseMissing((new PersonalAccessToken)->getTable(), [
            'tokenable_id' => $activeMember->id,
        ]);

        $reactivateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->patchJson("/api/v1/roles/{$role->id}/status", [
            'status' => 'active',
        ]);

        $reactivateResponse->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('tenant_memberships', [
            'id' => $activeMembership->id,
            'status' => 'active',
            'status_before_role_suspension' => null,
        ]);
        $this->assertDatabaseHas('tenant_memberships', [
            'id' => $inactiveMembership->id,
            'status' => 'inactive',
            'status_before_role_suspension' => null,
        ]);
    }

    public function test_only_the_company_owner_can_open_admin_role_settings_contract_and_update_it(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Admin Role Tenant',
            'slug' => 'admin-role-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
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
                'roles' => ['read', 'write'],
            ],
            'description' => 'System admin role.',
        ]);

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);
        $membership->roles()->attach($adminRole->id);

        Sanctum::actingAs($member, [], 'sanctum');

        $memberDetailResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->getJson("/api/v1/roles/{$adminRole->id}");

        $memberDetailResponse->assertOk()
            ->assertJsonPath('data.is_admin_role', true)
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.can_deactivate', false);

        $memberUpdateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/roles/{$adminRole->id}", [
            'key' => 'admin',
            'name' => 'Tenant Admin',
            'department' => 'Administration',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'global',
            'module_permissions' => [
                'roles' => ['read', 'write'],
            ],
            'description' => 'Updated by member.',
        ]);

        $memberUpdateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Only the company owner can edit the admin role.');

        Sanctum::actingAs($owner, [], 'sanctum');

        $ownerDetailResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->getJson("/api/v1/roles/{$adminRole->id}");

        $ownerDetailResponse->assertOk()
            ->assertJsonPath('data.is_admin_role', true)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.can_deactivate', false);

        $ownerUpdateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/roles/{$adminRole->id}", [
            'key' => 'admin',
            'name' => 'Tenant Admin',
            'department' => 'Leadership',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'global',
            'module_permissions' => [
                'roles' => ['read', 'write'],
                'users' => ['read', 'write'],
            ],
            'description' => 'Updated by owner.',
        ]);

        $ownerUpdateResponse->assertOk()
            ->assertJsonPath('data.name', 'Tenant Admin')
            ->assertJsonPath('data.department', 'Leadership')
            ->assertJsonPath('data.can_edit', true);
    }

    public function test_admin_role_cannot_be_deactivated_or_renamed_even_by_the_company_owner(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Admin Guard Tenant',
            'slug' => 'admin-guard-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
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
                'roles' => ['read', 'write'],
            ],
            'description' => 'System admin role.',
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $deactivateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->patchJson("/api/v1/roles/{$adminRole->id}/status", [
            'status' => 'inactive',
        ]);

        $deactivateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Admin role cannot be deactivated.');

        $renameKeyResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/roles/{$adminRole->id}", [
            'key' => 'workspace-admin',
            'name' => 'Workspace Admin',
            'department' => 'Administration',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'global',
            'module_permissions' => [
                'roles' => ['read', 'write'],
            ],
            'description' => 'Attempted key change.',
        ]);

        $renameKeyResponse->assertStatus(403)
            ->assertJsonPath('message', 'Admin role key cannot be changed.');
    }

    public function test_role_create_and_delete_are_authorized_separately_from_write(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Role Crud Split Tenant',
            'slug' => 'role-crud-split-tenant',
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

        $crudRole = Role::create([
            'tenant_id' => $tenant->id,
            'key' => 'role-curator',
            'name' => 'Role Curator',
            'department' => 'Administration',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'roles' => ['create', 'delete'],
            ],
            'description' => 'Can create and delete roles.',
        ]);

        $targetRole = Role::create([
            'tenant_id' => $tenant->id,
            'key' => 'temporary-role',
            'name' => 'Temporary Role',
            'department' => 'Administration',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'department',
            'module_permissions' => [
                'roles' => ['read'],
            ],
            'description' => 'Target role for deletion.',
        ]);

        $membership->roles()->attach($crudRole->id);

        Sanctum::actingAs($member, [], 'sanctum');

        $createResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/roles', [
            'key' => 'generated-role',
            'name' => 'Generated Role',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'department',
            'module_permissions' => [
                'roles' => ['read'],
            ],
            'description' => 'Created with create-only permission.',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.key', 'generated-role');

        $updateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/roles/{$targetRole->id}", [
            'name' => 'Renamed Role',
        ]);

        $updateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $deleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/roles/{$targetRole->id}");

        $deleteResponse->assertOk()
            ->assertJsonPath('message', 'Role deleted');
    }
}

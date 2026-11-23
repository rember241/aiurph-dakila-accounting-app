<?php

namespace Modules\Users\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_flags_are_exposed_in_user_list_for_other_admins(): void
    {
        [$tenant, $owner, $admin] = $this->createTenantWithOwnerAndAdmin();

        Sanctum::actingAs($admin, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => (string) $owner->id,
                'is_owner' => true,
                'is_current_user' => false,
                'can_edit' => false,
                'can_delete' => false,
            ])
            ->assertJsonFragment([
                'id' => (string) $admin->id,
                'is_owner' => false,
                'is_current_user' => true,
                'can_edit' => true,
                'can_delete' => false,
            ]);
    }

    public function test_read_only_members_can_view_users_but_receive_no_edit_or_delete_affordances(): void
    {
        [$tenant, $owner, $admin] = $this->createTenantWithOwnerAndAdmin();

        $viewer = User::factory()->create([
            'department' => 'Audit',
        ]);

        $viewerMembership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $viewer->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        $viewerRole = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => false,
            'key' => 'user-viewer',
            'name' => 'User Viewer',
            'department' => 'Audit',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'department',
            'module_permissions' => [
                'users' => ['read'],
            ],
            'description' => 'Can only view user records.',
        ]);

        $viewerMembership->roles()->attach($viewerRole->id);
        $viewer->forceFill(['company_id' => $tenant->id])->save();

        Sanctum::actingAs($viewer, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => (string) $admin->id,
                'can_edit' => false,
                'can_delete' => false,
            ])
            ->assertJsonFragment([
                'id' => (string) $viewer->id,
                'can_edit' => false,
                'can_delete' => false,
            ]);
    }

    public function test_other_admin_cannot_edit_or_delete_owner_account(): void
    {
        [$tenant, $owner, $admin, $adminRole] = $this->createTenantWithOwnerAndAdmin();

        Sanctum::actingAs($admin, [], 'sanctum');

        $updateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/users/{$owner->id}", [
            'name' => 'Changed Owner Name',
            'email' => $owner->email,
            'department' => 'Finance',
            'status' => 'active',
            'role_ids' => [(string) $adminRole->id],
        ]);

        $updateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Only the company owner can edit the owner account.');

        $deleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/users/{$owner->id}");

        $deleteResponse->assertStatus(403)
            ->assertJsonPath('message', 'The company owner cannot be removed from the company.');
    }

    public function test_owner_can_edit_their_own_account(): void
    {
        [$tenant, $owner, , $adminRole] = $this->createTenantWithOwnerAndAdmin();

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/users/{$owner->id}", [
            'name' => 'Owner Updated',
            'email' => $owner->email,
            'department' => 'Executive',
            'status' => 'active',
            'role_ids' => [(string) $adminRole->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $owner->id)
            ->assertJsonPath('data.name', 'Owner Updated')
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.is_current_user', true)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', false);
    }

    public function test_owner_cannot_be_deactivated_by_other_admin_or_self(): void
    {
        [$tenant, $owner, $admin] = $this->createTenantWithOwnerAndAdmin();

        Sanctum::actingAs($admin, [], 'sanctum');

        $adminTransitionResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->patchJson("/api/v1/users/{$owner->id}/status", [
            'status' => 'inactive',
        ]);

        $adminTransitionResponse->assertStatus(403)
            ->assertJsonPath('message', 'The company owner cannot be deactivated.');

        Sanctum::actingAs($owner, [], 'sanctum');

        $ownerTransitionResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->patchJson("/api/v1/users/{$owner->id}/status", [
            'status' => 'inactive',
        ]);

        $ownerTransitionResponse->assertStatus(403)
            ->assertJsonPath('message', 'The company owner cannot be deactivated.');

        $ownerUpdateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/users/{$owner->id}", [
            'name' => $owner->name,
            'email' => $owner->email,
            'department' => 'Executive',
            'status' => 'inactive',
            'role_ids' => [(string) $admin->tenantMemberships()->firstOrFail()->roles()->firstOrFail()->getKey()],
        ]);

        $ownerUpdateResponse->assertStatus(403)
            ->assertJsonPath('message', 'The company owner cannot be deactivated.');
    }

    public function test_deactivating_the_only_active_membership_revokes_the_users_tokens(): void
    {
        [$tenant, $owner, $admin] = $this->createTenantWithOwnerAndAdmin();

        $plainTextToken = $admin->createToken('web')->plainTextToken;

        $this->assertNotEmpty($plainTextToken);
        $this->assertDatabaseCount((new PersonalAccessToken())->getTable(), 1);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->patchJson("/api/v1/users/{$admin->id}/status", [
            'status' => 'inactive',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseMissing((new PersonalAccessToken())->getTable(), [
            'tokenable_id' => $admin->id,
        ]);
    }

    public function test_updating_a_user_to_inactive_revokes_tokens_when_it_is_their_only_active_access(): void
    {
        [$tenant, $owner, $admin, $role] = $this->createTenantWithOwnerAndAdmin();

        $plainTextToken = $admin->createToken('web')->plainTextToken;

        $this->assertNotEmpty($plainTextToken);
        $this->assertDatabaseCount((new PersonalAccessToken())->getTable(), 1);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'department' => 'Finance',
            'status' => 'inactive',
            'role_ids' => [(string) $role->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseMissing((new PersonalAccessToken())->getTable(), [
            'tokenable_id' => $admin->id,
        ]);
    }

    public function test_users_can_be_filtered_by_unassigned_department(): void
    {
        $owner = User::factory()->create([
            'department' => 'Executive',
        ]);
        $member = User::factory()->create([
            'department' => null,
        ]);

        $tenant = Tenant::create([
            'name' => 'Unassigned Department Tenant',
            'slug' => 'unassigned-department-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
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
                'users' => ['read', 'create', 'write', 'delete'],
            ],
            'description' => 'Tenant admin role.',
        ]);

        TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $owner->id)
            ->firstOrFail()
            ->roles()
            ->attach($adminRole->id);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->getJson('/api/v1/users?department=__unassigned__');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $member->id)
            ->assertJsonPath('data.0.department', null);
    }

    public function test_existing_user_from_another_company_cannot_be_added_to_the_current_company(): void
    {
        [$tenant, $owner, , $adminRole] = $this->createTenantWithOwnerAndAdmin();

        $otherOwner = User::factory()->create();
        $existingUser = User::factory()->create([
            'email' => 'assigned@example.com',
        ]);

        $otherCompany = Tenant::create([
            'name' => 'Existing Company',
            'slug' => 'existing-company-single-assignment',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $otherOwner->id,
        ]);

        TenantMembership::create([
            'tenant_id' => $otherCompany->id,
            'user_id' => $existingUser->id,
            'status' => 'active',
            'invited_by_user_id' => $otherOwner->id,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Company-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/users', [
            'name' => 'Assigned User',
            'email' => 'assigned@example.com',
            'status' => 'active',
            'department' => 'Finance',
            'role_ids' => [(string) $adminRole->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
        $this->assertSame(
            'This user is already assigned to another company.',
            data_get($response->json(), 'errors.email.0')
        );

        $this->assertSame(
            1,
            TenantMembership::query()
                ->where('user_id', $existingUser->id)
                ->count()
        );
        $this->assertDatabaseMissing('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $existingUser->id,
        ]);
    }

    public function test_user_creation_persists_company_and_org_unit_assignments(): void
    {
        [$tenant, $owner, , $adminRole] = $this->createTenantWithOwnerAndAdmin();

        $rootOrgUnit = OrgUnit::create([
            'company_id' => $tenant->id,
            'type' => 'head_office',
            'name' => 'Head Office',
            'status' => 'active',
            'is_root' => true,
        ]);

        $teamOrgUnit = OrgUnit::create([
            'company_id' => $tenant->id,
            'parent_org_unit_id' => $rootOrgUnit->getKey(),
            'type' => 'team',
            'name' => 'Shared Services',
            'status' => 'active',
            'is_root' => false,
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Company-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/users', [
            'name' => 'Org Scoped User',
            'email' => 'org-scoped@example.com',
            'password' => 'password123',
            'status' => 'active',
            'department' => 'Operations',
            'primary_org_unit_id' => (string) $teamOrgUnit->getKey(),
            'org_unit_ids' => [
                (string) $rootOrgUnit->getKey(),
                (string) $teamOrgUnit->getKey(),
            ],
            'role_ids' => [(string) $adminRole->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.company_id', (string) $tenant->id)
            ->assertJsonPath('data.primary_org_unit.id', (string) $teamOrgUnit->getKey())
            ->assertJsonCount(2, 'data.org_units');

        $createdUser = User::query()->where('email', 'org-scoped@example.com')->firstOrFail();

        $this->assertSame((string) $tenant->id, (string) $createdUser->company_id);
        $this->assertSame((string) $teamOrgUnit->getKey(), (string) $createdUser->primary_org_unit_id);
        $this->assertDatabaseHas('user_org_unit_assignments', [
            'user_id' => $createdUser->id,
            'company_id' => $tenant->id,
            'org_unit_id' => $rootOrgUnit->getKey(),
        ]);
        $this->assertDatabaseHas('user_org_unit_assignments', [
            'user_id' => $createdUser->id,
            'company_id' => $tenant->id,
            'org_unit_id' => $teamOrgUnit->getKey(),
        ]);
    }

    public function test_user_creation_rejects_org_units_from_another_company(): void
    {
        [$tenant, $owner, , $adminRole] = $this->createTenantWithOwnerAndAdmin();

        $foreignOwner = User::factory()->create();
        $foreignCompany = Tenant::create([
            'name' => 'Foreign Company',
            'slug' => 'foreign-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $foreignOwner->id,
        ]);

        $foreignOrgUnit = OrgUnit::create([
            'company_id' => $foreignCompany->id,
            'type' => 'branch',
            'name' => 'Foreign Branch',
            'status' => 'active',
            'is_root' => false,
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Company-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/users', [
            'name' => 'Invalid Org User',
            'email' => 'invalid-org@example.com',
            'password' => 'password123',
            'status' => 'active',
            'primary_org_unit_id' => (string) $foreignOrgUnit->getKey(),
            'role_ids' => [(string) $adminRole->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_org_unit_id']);
    }

    public function test_user_creation_rejects_multiple_roles(): void
    {
        [$tenant, $owner, , $adminRole] = $this->createTenantWithOwnerAndAdmin();

        $viewerRole = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => true,
            'key' => 'viewer',
            'name' => 'Viewer',
            'department' => 'Administration',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'global',
            'module_permissions' => [
                'users' => ['read'],
            ],
            'description' => 'Read-only company viewer.',
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Company-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/users', [
            'name' => 'Too Many Roles',
            'email' => 'too-many-roles@example.com',
            'password' => 'password123',
            'status' => 'active',
            'role_ids' => [
                (string) $adminRole->id,
                (string) $viewerRole->id,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_ids']);
    }

    public function test_user_update_rejects_multiple_roles(): void
    {
        [$tenant, $owner, $admin, $adminRole] = $this->createTenantWithOwnerAndAdmin();

        $viewerRole = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => true,
            'key' => 'viewer',
            'name' => 'Viewer',
            'department' => 'Administration',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'global',
            'module_permissions' => [
                'users' => ['read'],
            ],
            'description' => 'Read-only company viewer.',
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'status' => 'active',
            'role_ids' => [
                (string) $adminRole->id,
                (string) $viewerRole->id,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_ids']);
    }

    public function test_user_delete_is_authorized_separately_from_create_and_write(): void
    {
        [$tenant, $owner, , $adminRole] = $this->createTenantWithOwnerAndAdmin();

        $actor = User::factory()->create([
            'department' => 'Operations',
        ]);
        $victim = User::factory()->create([
            'department' => 'Support',
        ]);

        $actorMembership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $actor->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        $victimMembership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $victim->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        $deleteRole = Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => false,
            'key' => 'user-remover',
            'name' => 'User Remover',
            'department' => 'Operations',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'users' => ['delete'],
            ],
            'description' => 'Can remove users from the company.',
        ]);

        $actorMembership->roles()->attach($deleteRole->id);
        $victimMembership->roles()->attach($adminRole->id);

        $actor->forceFill(['company_id' => $tenant->id])->save();
        $victim->forceFill(['company_id' => $tenant->id])->save();

        Sanctum::actingAs($actor, [], 'sanctum');

        $createResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/users', [
            'name' => 'Blocked Create',
            'email' => 'blocked-create@example.com',
            'password' => 'password123',
            'status' => 'active',
            'role_ids' => [(string) $adminRole->id],
        ]);

        $createResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $deleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/users/{$victim->id}");

        $deleteResponse->assertOk()
            ->assertJsonPath('message', 'User removed from company');
    }

    /**
     * @return array{0: Tenant, 1: User, 2: User, 3: Role}
     */
    private function createTenantWithOwnerAndAdmin(): array
    {
        $owner = User::factory()->create([
            'department' => 'Executive',
        ]);
        $admin = User::factory()->create([
            'department' => 'Finance',
        ]);

        $tenant = Tenant::create([
            'name' => 'User Guard Tenant',
            'slug' => 'user-guard-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $ownerMembership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        $adminMembership = TenantMembership::create([
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
                'users' => ['read', 'create', 'write', 'delete'],
            ],
            'description' => 'Tenant admin role.',
        ]);

        $ownerMembership->roles()->attach($adminRole->id);
        $adminMembership->roles()->attach($adminRole->id);

        $owner->forceFill(['company_id' => $tenant->id])->save();
        $admin->forceFill(['company_id' => $tenant->id])->save();

        return [$tenant, $owner, $admin, $adminRole];
    }
}

<?php

namespace Modules\Users\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class CrudAbilityEnforcementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_routes_require_exact_create_write_and_delete_permissions(): void
    {
        [$tenant, $owner, $member] = $this->createTenantWithMember();
        $existingRole = $this->createRole($tenant, 'existing-role', ['roles' => ['read']]);

        Sanctum::actingAs($member, [], 'sanctum');

        $createOnlyRole = $this->createRole($tenant, 'role-creator', ['roles' => ['create']]);
        $this->syncMemberRoles($tenant, $member, [$createOnlyRole->id]);

        $createResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/roles', [
            'key' => 'created-role',
            'name' => 'Created Role',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'roles' => ['read'],
            ],
            'description' => 'Created through create permission.',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.key', 'created-role');

        $blockedUpdateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/roles/{$existingRole->id}", [
            'name' => 'Blocked Update',
        ]);

        $blockedUpdateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $blockedDeleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/roles/{$existingRole->id}");

        $blockedDeleteResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $writeOnlyRole = $this->createRole($tenant, 'role-editor', ['roles' => ['write']]);
        $this->syncMemberRoles($tenant, $member, [$writeOnlyRole->id]);

        $blockedCreateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/roles', [
            'key' => 'blocked-role',
            'name' => 'Blocked Role',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'roles' => ['read'],
            ],
            'description' => 'Should not be created.',
        ]);

        $blockedCreateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $writeResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/roles/{$existingRole->id}", [
            'name' => 'Write Allowed',
        ]);

        $writeResponse->assertOk()
            ->assertJsonPath('data.name', 'Write Allowed');

        $deleteTarget = $this->createRole($tenant, 'delete-target', ['roles' => ['read']]);
        $deleteOnlyRole = $this->createRole($tenant, 'role-deleter', ['roles' => ['delete']]);
        $this->syncMemberRoles($tenant, $member, [$deleteOnlyRole->id]);

        $blockedDeleteUpdateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/roles/{$deleteTarget->id}", [
            'name' => 'Should Stay Untouched',
        ]);

        $blockedDeleteUpdateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $deleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/roles/{$deleteTarget->id}");

        $deleteResponse->assertOk()
            ->assertJsonPath('message', 'Role deleted');
    }

    public function test_user_routes_require_exact_create_write_and_delete_permissions(): void
    {
        [$tenant, $owner, $member] = $this->createTenantWithMember();

        $readRole = $this->createRole($tenant, 'user-viewer', ['users' => ['read']]);
        $editableUser = User::factory()->create([
            'email' => 'editable-user@example.com',
        ]);

        $editableMembership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $editableUser->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);
        $editableMembership->roles()->attach($readRole->id);
        $editableUser->forceFill(['company_id' => $tenant->id])->save();

        Sanctum::actingAs($member, [], 'sanctum');

        $createOnlyRole = $this->createRole($tenant, 'user-creator', ['users' => ['create']]);
        $this->syncMemberRoles($tenant, $member, [$createOnlyRole->id]);

        $createResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/users', [
            'name' => 'Created User',
            'email' => 'created-user@example.com',
            'password' => 'password123',
            'status' => 'active',
            'role_ids' => [(string) $readRole->id],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.email', 'created-user@example.com');

        $blockedUpdateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/users/{$editableUser->id}", [
            'name' => 'Blocked Update',
            'email' => $editableUser->email,
            'status' => 'active',
            'role_ids' => [(string) $readRole->id],
        ]);

        $blockedUpdateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $blockedDeleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/users/{$editableUser->id}");

        $blockedDeleteResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $writeOnlyRole = $this->createRole($tenant, 'user-editor', ['users' => ['write']]);
        $this->syncMemberRoles($tenant, $member, [$writeOnlyRole->id]);

        $blockedCreateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/users', [
            'name' => 'Blocked Create User',
            'email' => 'blocked-create-user@example.com',
            'password' => 'password123',
            'status' => 'active',
            'role_ids' => [(string) $readRole->id],
        ]);

        $blockedCreateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $writeResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/users/{$editableUser->id}", [
            'name' => 'Write Allowed User',
            'email' => $editableUser->email,
            'status' => 'active',
            'role_ids' => [(string) $readRole->id],
        ]);

        $writeResponse->assertOk()
            ->assertJsonPath('data.name', 'Write Allowed User');

        $deleteTarget = User::factory()->create([
            'email' => 'delete-target-user@example.com',
        ]);
        $deleteMembership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $deleteTarget->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);
        $deleteMembership->roles()->attach($readRole->id);
        $deleteTarget->forceFill(['company_id' => $tenant->id])->save();

        $deleteOnlyRole = $this->createRole($tenant, 'user-deleter', ['users' => ['delete']]);
        $this->syncMemberRoles($tenant, $member, [$deleteOnlyRole->id]);

        $blockedDeleteUpdateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/users/{$deleteTarget->id}", [
            'name' => 'Should Stay Untouched',
            'email' => $deleteTarget->email,
            'status' => 'active',
            'role_ids' => [(string) $readRole->id],
        ]);

        $blockedDeleteUpdateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $deleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/users/{$deleteTarget->id}");

        $deleteResponse->assertOk()
            ->assertJsonPath('message', 'User removed from company');
    }

    public function test_org_unit_routes_require_exact_create_write_and_delete_permissions(): void
    {
        [$tenant, $owner, $member] = $this->createTenantWithMember();

        $root = OrgUnit::create([
            'company_id' => $tenant->id,
            'type' => 'head_office',
            'name' => 'Head Office',
            'status' => 'active',
            'is_root' => true,
        ]);

        $branch = OrgUnit::create([
            'company_id' => $tenant->id,
            'parent_org_unit_id' => $root->getKey(),
            'type' => 'branch',
            'name' => 'Editable Branch',
            'status' => 'active',
            'is_root' => false,
        ]);

        Sanctum::actingAs($member, [], 'sanctum');

        $createOnlyRole = $this->createRole($tenant, 'org-unit-creator', ['org_units' => ['create']]);
        $this->syncMemberRoles($tenant, $member, [$createOnlyRole->id]);

        $createResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/org-units', [
            'name' => 'Created Branch',
            'code' => 'CREATED-BRANCH',
            'type' => 'branch',
            'status' => 'active',
            'parent_org_unit_id' => (string) $root->getKey(),
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.name', 'Created Branch');

        $blockedUpdateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/org-units/{$branch->getKey()}", [
            'name' => 'Blocked Update',
            'code' => 'BLOCKED-UPDATE',
            'type' => 'branch',
            'status' => 'active',
            'parent_org_unit_id' => (string) $root->getKey(),
        ]);

        $blockedUpdateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $blockedDeleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/org-units/{$branch->getKey()}");

        $blockedDeleteResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $writeOnlyRole = $this->createRole($tenant, 'org-unit-editor', ['org_units' => ['write']]);
        $this->syncMemberRoles($tenant, $member, [$writeOnlyRole->id]);

        $blockedCreateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/v1/org-units', [
            'name' => 'Blocked Create',
            'code' => 'BLOCKED-CREATE',
            'type' => 'branch',
            'status' => 'active',
            'parent_org_unit_id' => (string) $root->getKey(),
        ]);

        $blockedCreateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $writeResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/org-units/{$branch->getKey()}", [
            'name' => 'Write Allowed Branch',
            'code' => 'WRITE-ALLOWED',
            'type' => 'branch',
            'status' => 'active',
            'parent_org_unit_id' => (string) $root->getKey(),
        ]);

        $writeResponse->assertOk()
            ->assertJsonPath('data.name', 'Write Allowed Branch');

        $deleteTarget = OrgUnit::create([
            'company_id' => $tenant->id,
            'parent_org_unit_id' => $root->getKey(),
            'type' => 'team',
            'name' => 'Delete Target',
            'status' => 'active',
            'is_root' => false,
        ]);

        $deleteOnlyRole = $this->createRole($tenant, 'org-unit-deleter', ['org_units' => ['delete']]);
        $this->syncMemberRoles($tenant, $member, [$deleteOnlyRole->id]);

        $blockedDeleteUpdateResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->putJson("/api/v1/org-units/{$deleteTarget->getKey()}", [
            'name' => 'Should Stay Untouched',
            'code' => 'SHOULD-STAY',
            'type' => 'team',
            'status' => 'active',
            'parent_org_unit_id' => (string) $root->getKey(),
        ]);

        $blockedDeleteUpdateResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $deleteResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->deleteJson("/api/v1/org-units/{$deleteTarget->getKey()}");

        $deleteResponse->assertOk()
            ->assertJsonPath('message', 'Organization unit deleted');
    }

    /**
     * @return array{0: Tenant, 1: User, 2: User}
     */
    private function createTenantWithMember(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'CRUD Ability Tenant',
            'slug' => 'crud-ability-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        $member->forceFill(['company_id' => $tenant->id])->save();

        return [$tenant, $owner, $member];
    }

    /**
     * @param array<string, array<int, string>> $modulePermissions
     */
    private function createRole(Tenant $tenant, string $key, array $modulePermissions): Role
    {
        return Role::create([
            'tenant_id' => $tenant->id,
            'key' => $key,
            'name' => str($key)->headline()->toString(),
            'department' => 'Operations',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => $modulePermissions,
            'description' => 'Scoped CRUD role.',
        ]);
    }

    /**
     * @param array<int, string> $roleIds
     */
    private function syncMemberRoles(Tenant $tenant, User $member, array $roleIds): void
    {
        TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $member->id)
            ->firstOrFail()
            ->roles()
            ->sync($roleIds);
    }
};

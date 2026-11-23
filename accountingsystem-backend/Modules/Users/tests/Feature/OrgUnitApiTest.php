<?php

namespace Modules\Users\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Models\Tenant;
use Tests\TestCase;

class OrgUnitApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_an_org_unit_under_the_current_company(): void
    {
        $owner = User::factory()->create();
        $company = $this->createCompany($owner, 'Acme Company');
        $root = $this->createOrgUnit($company, 'Head Office', 'head_office', null, true);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->postJson('/api/v1/org-units', [
            'name' => 'Cebu Branch',
            'code' => 'BR-CEBU',
            'type' => 'branch',
            'status' => 'active',
            'parent_org_unit_id' => (string) $root->getKey(),
            'location' => 'Cebu City',
            'description' => 'Regional operating branch.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.company_id', (string) $company->getKey())
            ->assertJsonPath('data.parent_org_unit_id', (string) $root->getKey())
            ->assertJsonPath('data.parent.id', (string) $root->getKey())
            ->assertJsonPath('data.type', 'branch')
            ->assertJsonPath('data.name', 'Cebu Branch')
            ->assertJsonPath('data.code', 'BR-CEBU')
            ->assertJsonPath('data.location', 'Cebu City')
            ->assertJsonPath('data.description', 'Regional operating branch.')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_root', false);

        $this->assertDatabaseHas('org_units', [
            'company_id' => $company->getKey(),
            'parent_org_unit_id' => $root->getKey(),
            'type' => 'branch',
            'name' => 'Cebu Branch',
            'code' => 'BR-CEBU',
            'location' => 'Cebu City',
            'description' => 'Regional operating branch.',
            'status' => 'active',
            'is_root' => false,
        ]);
    }

    public function test_create_rejects_cross_company_parent_relationships(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $company = $this->createCompany($owner, 'Primary Company');
        $otherCompany = $this->createCompany($otherOwner, 'Other Company');
        $foreignParent = $this->createOrgUnit($otherCompany, 'Foreign Branch', 'branch');

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->postJson('/api/v1/org-units', [
            'name' => 'Finance Team',
            'code' => 'TEAM-FIN',
            'type' => 'team',
            'parent_org_unit_id' => (string) $foreignParent->getKey(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_org_unit_id']);

        $this->assertDatabaseMissing('org_units', [
            'company_id' => $company->getKey(),
            'name' => 'Finance Team',
        ]);
    }

    public function test_index_and_stats_are_scoped_to_the_current_company(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $company = $this->createCompany($owner, 'Scoped Company');
        $otherCompany = $this->createCompany($otherOwner, 'Hidden Company');

        $root = $this->createOrgUnit($company, 'Head Office', 'head_office', null, true, 'active');
        $branch = $this->createOrgUnit($company, 'Makati Branch', 'branch', $root, false, 'active');
        $this->createOrgUnit($company, 'Operations', 'department', $branch, false, 'inactive');
        $this->createOrgUnit($otherCompany, 'Other Branch', 'branch', null, false, 'active');

        Sanctum::actingAs($owner, [], 'sanctum');

        $listResponse = $this->getJson('/api/v1/org-units?type=branch&status=active');

        $listResponse->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $branch->getKey())
            ->assertJsonPath('data.0.company_id', (string) $company->getKey());

        $statsResponse = $this->getJson('/api/v1/org-units/stats');

        $statsResponse->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.active', 2)
            ->assertJsonPath('data.inactive', 1)
            ->assertJsonPath('data.root', 1);
    }

    public function test_show_returns_not_found_for_an_org_unit_outside_the_current_company(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $company = $this->createCompany($owner, 'Visible Company');
        $hiddenCompany = $this->createCompany($otherOwner, 'Hidden Company');
        $hiddenOrgUnit = $this->createOrgUnit($hiddenCompany, 'Hidden Branch', 'branch');
        $this->createOrgUnit($company, 'Head Office', 'head_office', null, true);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->getJson("/api/v1/org-units/{$hiddenOrgUnit->getKey()}");

        $response->assertNotFound();
    }

    public function test_org_unit_can_be_updated_with_a_valid_parent_change(): void
    {
        $owner = User::factory()->create();
        $company = $this->createCompany($owner, 'Update Company');
        $root = $this->createOrgUnit($company, 'Head Office', 'head_office', null, true);
        $branch = $this->createOrgUnit($company, 'Quezon Branch', 'branch', $root);
        $team = $this->createOrgUnit($company, 'Legacy Team', 'team');

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->putJson("/api/v1/org-units/{$team->getKey()}", [
            'name' => 'Operations Team',
            'code' => 'TEAM-OPS',
            'type' => 'team',
            'parent_org_unit_id' => (string) $branch->getKey(),
            'location' => 'Quezon City',
            'description' => 'Shared services support team.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $team->getKey())
            ->assertJsonPath('data.name', 'Operations Team')
            ->assertJsonPath('data.code', 'TEAM-OPS')
            ->assertJsonPath('data.location', 'Quezon City')
            ->assertJsonPath('data.description', 'Shared services support team.')
            ->assertJsonPath('data.parent_org_unit_id', (string) $branch->getKey());

        $this->assertDatabaseHas('org_units', [
            'id' => $team->getKey(),
            'name' => 'Operations Team',
            'code' => 'TEAM-OPS',
            'location' => 'Quezon City',
            'description' => 'Shared services support team.',
            'parent_org_unit_id' => $branch->getKey(),
        ]);
    }

    public function test_update_rejects_hierarchy_cycles(): void
    {
        $owner = User::factory()->create();
        $company = $this->createCompany($owner, 'Cycle Company');
        $root = $this->createOrgUnit($company, 'Head Office', 'head_office', null, true);
        $branch = $this->createOrgUnit($company, 'North Branch', 'branch', $root);
        $team = $this->createOrgUnit($company, 'North Team', 'team', $branch);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->putJson("/api/v1/org-units/{$branch->getKey()}", [
            'parent_org_unit_id' => (string) $team->getKey(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_org_unit_id']);
    }

    public function test_status_transition_updates_state_and_records_audit(): void
    {
        $owner = User::factory()->create();
        $company = $this->createCompany($owner, 'Status Company');
        $branch = $this->createOrgUnit($company, 'Laguna Branch', 'branch');

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->patchJson("/api/v1/org-units/{$branch->getKey()}/status", [
            'status' => 'inactive',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('org_units', [
            'id' => $branch->getKey(),
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org_unit.deactivated',
            'tenant_id' => $company->getKey(),
            'auditable_id' => $branch->getKey(),
        ]);
    }

    public function test_delete_blocks_root_and_parent_org_units_but_allows_leaf_units(): void
    {
        $owner = User::factory()->create();
        $company = $this->createCompany($owner, 'Delete Company');
        $root = $this->createOrgUnit($company, 'Head Office', 'head_office', null, true);
        $branch = $this->createOrgUnit($company, 'Davao Branch', 'branch', $root);
        $team = $this->createOrgUnit($company, 'Davao Team', 'team', $branch);

        Sanctum::actingAs($owner, [], 'sanctum');

        $branchDeleteResponse = $this->deleteJson("/api/v1/org-units/{$branch->getKey()}");
        $branchDeleteResponse->assertStatus(409)
            ->assertJsonPath('message', 'Organization unit has child organization units and cannot be deleted.');

        $rootDeleteResponse = $this->deleteJson("/api/v1/org-units/{$root->getKey()}");
        $rootDeleteResponse->assertStatus(403)
            ->assertJsonPath('message', 'The root organization unit cannot be deleted.');

        $teamDeleteResponse = $this->deleteJson("/api/v1/org-units/{$team->getKey()}");
        $teamDeleteResponse->assertOk()
            ->assertJsonPath('message', 'Organization unit deleted');

        $this->assertDatabaseMissing('org_units', [
            'id' => $team->getKey(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org_unit.deleted',
            'tenant_id' => $company->getKey(),
            'auditable_id' => $team->getKey(),
        ]);
    }

    public function test_delete_blocks_org_units_with_assigned_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->createCompany($owner, 'People Company');
        $root = $this->createOrgUnit($company, 'Head Office', 'head_office', null, true);
        $department = $this->createOrgUnit($company, 'Finance Department', 'department', $root);

        $member->forceFill([
            'company_id' => $company->getKey(),
            'primary_org_unit_id' => $department->getKey(),
        ])->save();
        $member->orgUnits()->attach($department->getKey(), [
            'company_id' => $company->getKey(),
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->deleteJson("/api/v1/org-units/{$department->getKey()}");

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Organization unit has assigned members and cannot be deleted.');

        $this->assertDatabaseHas('org_units', [
            'id' => $department->getKey(),
        ]);
    }

    public function test_org_unit_create_is_authorized_separately_from_write(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->createCompany($owner, 'Create Only Org Unit Company');
        $root = $this->createOrgUnit($company, 'Head Office', 'head_office', null, true);

        $membership = \Modules\Users\Models\TenantMembership::create([
            'tenant_id' => $company->getKey(),
            'user_id' => $member->getKey(),
            'status' => 'active',
            'invited_by_user_id' => $owner->getKey(),
            'joined_at' => now(),
        ]);

        $createRole = \Modules\Users\Models\Role::create([
            'tenant_id' => $company->getKey(),
            'key' => 'org-unit-creator',
            'name' => 'Org Unit Creator',
            'department' => 'Operations',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'department',
            'module_permissions' => [
                'org_units' => ['create'],
            ],
            'description' => 'Can create org units without edit rights.',
        ]);

        $membership->roles()->attach($createRole->getKey());

        Sanctum::actingAs($member, [], 'sanctum');

        $createResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $company->getKey(),
        ])->postJson('/api/v1/org-units', [
            'name' => 'Cagayan Branch',
            'code' => 'BR-CDO',
            'type' => 'branch',
            'status' => 'active',
            'parent_org_unit_id' => (string) $root->getKey(),
            'location' => 'Cagayan de Oro',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.name', 'Cagayan Branch');

        $orgUnitId = (string) $createResponse->json('data.id');

        $statusResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $company->getKey(),
        ])->patchJson("/api/v1/org-units/{$orgUnitId}/status", [
            'status' => 'inactive',
        ]);

        $statusResponse->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');
    }

    private function createCompany(User $owner, string $name): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->getKey(),
        ]);
    }

    private function createOrgUnit(
        Tenant $company,
        string $name,
        string $type,
        ?OrgUnit $parent = null,
        bool $isRoot = false,
        string $status = 'active',
    ): OrgUnit {
        return OrgUnit::create([
            'company_id' => $company->getKey(),
            'parent_org_unit_id' => $parent?->getKey(),
            'type' => $type,
            'name' => $name,
            'status' => $status,
            'is_root' => $isRoot,
        ]);
    }
}

<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class PeriodManagementAuthorizationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_management_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/accounting/period-management/fiscal-years');

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_period_management_requires_period_read_ability(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Period Contract Shared Company',
            'slug' => 'period-contract-shared-company',
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

        Sanctum::actingAs($member, [], 'sanctum');

        FiscalYear::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $tenant->id,
            'code' => 'FY2080',
            'name' => 'Fiscal Year 2080',
            'start_date' => '2080-01-01',
            'end_date' => '2080-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
        ])->getJson('/api/v1/accounting/period-management/fiscal-years');

        $response->assertForbidden()
            ->assertJsonPath('message', 'Insufficient company permissions.');
    }

    public function test_period_management_write_routes_require_period_write_ability(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create([
            'company_id' => null,
        ]);

        $tenant = Tenant::create([
            'name' => 'Period Contract Write Company',
            'slug' => 'period-contract-write-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $member->forceFill(['company_id' => $tenant->id])->save();

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        $readOnlyRole = \Modules\Users\Models\Role::create([
            'tenant_id' => $tenant->id,
            'system_generated' => false,
            'key' => 'period-read-only',
            'name' => 'Period Read Only',
            'department' => 'Finance',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'department',
            'module_permissions' => [
                'period' => ['read'],
            ],
            'description' => 'Read-only period role.',
        ]);

        $membership->roles()->attach($readOnlyRole->id);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->postJson('/api/v1/accounting/period-management/fiscal-years', [
            'code' => 'FY2080-WRITE',
            'name' => 'Fiscal Year 2080 Write',
            'start_date' => '2080-01-01',
            'end_date' => '2080-12-31',
            'is_current' => true,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Insufficient company permissions.');
    }

    public function test_period_management_resolves_the_authenticated_users_company_without_a_header(): void
    {
        [$tenant] = $this->actingAsCompanyUser(['period.read']);

        $fiscalYear = FiscalYear::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $tenant->id,
            'code' => 'FY2081',
            'name' => 'Fiscal Year 2081',
            'start_date' => '2081-01-01',
            'end_date' => '2081-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);

        $response = $this->getJson('/api/v1/accounting/period-management/fiscal-years/current');

        $response->assertOk()
            ->assertJsonPath('id', (string) $fiscalYear->id);
    }

    public function test_period_management_rejects_mismatched_company_headers(): void
    {
        [$tenant, $headers] = $this->actingAsCompanyUser(['period.read']);

        $otherOwner = User::factory()->create();
        $otherTenant = Tenant::create([
            'name' => 'Mismatched Company',
            'slug' => 'mismatched-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $otherOwner->id,
        ]);

        $response = $this->withHeaders([
            ...$headers,
            'X-Tenant-ID' => (string) $otherTenant->id,
        ])->getJson('/api/v1/accounting/period-management/fiscal-years');

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Company switching is no longer supported for this account.');
    }

    /**
     * @param array<int, string> $abilities
     * @return array{0: Tenant, 1: array<string, string>}
     */
    private function actingAsCompanyUser(array $abilities): array
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, $abilities, 'sanctum');

        $tenant = Tenant::create([
            'name' => 'Period Contract Company '.$user->id,
            'slug' => 'period-contract-company-'.$user->id,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        return [
            $tenant,
            [
                'X-Tenant-ID' => (string) $tenant->id,
            ],
        ];
    }
}

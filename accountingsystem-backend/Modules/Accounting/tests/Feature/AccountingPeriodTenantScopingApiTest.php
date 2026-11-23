<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Users\Models\Tenant;
use Tests\TestCase;

class AccountingPeriodTenantScopingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_periods_inherit_tenant_id_from_fiscal_year(): void
    {
        [$tenant, $headers] = $this->actingAsTenantUser(['period.write']);

        $response = $this->withHeaders($headers)->postJson('/api/v1/accounting/period-management/fiscal-years', [
            'code' => 'FY2041',
            'name' => 'Fiscal Year 2041',
            'start_date' => '2041-01-01',
            'end_date' => '2041-12-31',
            'is_current' => true,
        ]);

        $response->assertCreated();

        $fiscalYearId = (string)$response->json('id');

        $this->assertSame(
            12,
            AccountingPeriod::query()
                ->where('tenant_id', $tenant->id)
                ->where('fiscal_year_id', $fiscalYearId)
                ->count()
        );
    }

    public function test_period_listing_is_scoped_to_the_active_tenant(): void
    {
        [$tenantA, $headersA] = $this->actingAsTenantUser(['period.read']);
        $otherOwner = User::factory()->create();
        $tenantB = $this->createTenant($otherOwner, 'period-scope-b');

        $fiscalYearA = $this->createFiscalYearForTenant($tenantA, [
            'code' => 'FY2042-A',
            'name' => 'Tenant A Year',
            'is_current' => true,
        ]);
        $fiscalYearB = $this->createFiscalYearForTenant($tenantB, [
            'code' => 'FY2042-B',
            'name' => 'Tenant B Year',
            'is_current' => true,
        ]);

        $periodA = AccountingPeriod::query()->create([
            'tenant_id' => $tenantA->id,
            'fiscal_year_id' => $fiscalYearA->id,
            'period_number' => 1,
            'name' => 'Jan 2042',
            'start_date' => '2042-01-01',
            'end_date' => '2042-01-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);

        AccountingPeriod::query()->create([
            'tenant_id' => $tenantB->id,
            'fiscal_year_id' => $fiscalYearB->id,
            'period_number' => 1,
            'name' => 'Jan 2042 B',
            'start_date' => '2042-01-01',
            'end_date' => '2042-01-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);

        $this->withHeaders($headersA)
            ->getJson('/api/v1/accounting/period-management/periods?pageSize=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', (string)$periodA->id)
            ->assertJsonPath('data.0.tenant_id', (string)$tenantA->id);
    }

    public function test_cannot_transition_another_tenants_period_by_id(): void
    {
        [$tenantA, $headersA] = $this->actingAsTenantUser(['period.write']);
        $otherOwner = User::factory()->create();
        $tenantB = $this->createTenant($otherOwner, 'period-scope-c');
        $fiscalYearB = $this->createFiscalYearForTenant($tenantB, [
            'code' => 'FY2043-B',
            'name' => 'Tenant B Year',
            'is_current' => true,
        ]);

        $tenantBPeriod = AccountingPeriod::query()->create([
            'tenant_id' => $tenantB->id,
            'fiscal_year_id' => $fiscalYearB->id,
            'period_number' => 1,
            'name' => 'Jan 2043 B',
            'start_date' => '2043-01-01',
            'end_date' => '2043-01-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);

        $this->withHeaders($headersA)
            ->patchJson("/api/v1/accounting/period-management/periods/{$tenantBPeriod->id}/status", [
                'status' => 'closed',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('accounting_periods', [
            'id' => $tenantBPeriod->id,
            'tenant_id' => $tenantB->id,
            'status' => 'open',
        ]);
    }

    public function test_period_model_syncs_tenant_id_from_fiscal_year(): void
    {
        [$tenant, ] = $this->actingAsTenantUser(['period.write']);
        $fiscalYear = $this->createFiscalYearForTenant($tenant, [
            'code' => 'FY2044',
            'name' => 'Tenant Synced Year',
            'is_current' => true,
        ]);

        $period = AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 1,
            'name' => 'Jan 2044',
            'start_date' => '2044-01-01',
            'end_date' => '2044-01-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);

        $this->assertSame((string)$tenant->id, (string)$period->tenant_id);
    }

    /**
     * @return array{0: Tenant, 1: array<string, string>}
     */
    private function actingAsTenantUser(array $abilities): array
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, $abilities, 'sanctum');

        $tenant = Tenant::create([
            'name' => 'Period Scope '.$user->id,
            'slug' => 'period-scope-'.$user->id,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        return [
            $tenant,
            [
                'X-Tenant-ID' => (string)$tenant->id,
            ],
        ];
    }

    private function createTenant(User $user, string $slug): Tenant
    {
        return Tenant::create([
            'name' => 'Period Scope '.strtoupper($slug),
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createFiscalYearForTenant(Tenant $tenant, array $attributes): FiscalYear
    {
        return FiscalYear::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $tenant->id,
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'start_date' => $attributes['start_date'] ?? '2042-01-01',
            'end_date' => $attributes['end_date'] ?? '2042-12-31',
            'status' => $attributes['status'] ?? 'open',
            'is_current' => $attributes['is_current'] ?? false,
        ]);
    }
}

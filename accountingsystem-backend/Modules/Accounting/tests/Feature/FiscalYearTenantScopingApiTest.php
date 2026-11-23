<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Users\Models\Tenant;
use Tests\TestCase;

class FiscalYearTenantScopingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_fiscal_year_code_and_range_are_allowed_across_different_companies(): void
    {
        $userA = $this->actingAsUserWithAbilities(['period.write']);
        $tenantA = $this->createTenant($userA, 'company-a-2035');
        $headersA = $this->tenantHeaders($tenantA);

        $payload = [
            'code' => 'FY2035',
            'name' => 'Fiscal Year 2035',
            'start_date' => '2035-01-01',
            'end_date' => '2035-12-31',
            'is_current' => true,
        ];

        $this->withHeaders($headersA)
            ->postJson('/api/v1/accounting/period-management/fiscal-years', $payload)
            ->assertCreated();

        $userB = $this->actingAsUserWithAbilities(['period.write']);
        $tenantB = $this->createTenant($userB, 'company-b-2035');
        $headersB = $this->tenantHeaders($tenantB);

        $this->withHeaders($headersB)
            ->postJson('/api/v1/accounting/period-management/fiscal-years', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('fiscal_years', [
            'tenant_id' => $tenantA->id,
            'code' => 'FY2035',
        ]);
        $this->assertDatabaseHas('fiscal_years', [
            'tenant_id' => $tenantB->id,
            'code' => 'FY2035',
        ]);
        $this->assertSame(
            2,
            FiscalYear::query()
                ->where('code', 'FY2035')
                ->count()
        );
    }

    public function test_duplicate_fiscal_year_code_is_rejected_within_same_tenant(): void
    {
        $user = $this->actingAsUserWithAbilities(['period.write']);
        $headers = $this->tenantHeaders($this->createTenant($user, 'company-2036'));

        $this->withHeaders($headers)->postJson('/api/v1/accounting/period-management/fiscal-years', [
            'code' => 'FY2036',
            'name' => 'Fiscal Year 2036',
            'start_date' => '2036-01-01',
            'end_date' => '2036-12-31',
            'is_current' => true,
        ])->assertCreated();

        $response = $this->withHeaders($headers)->postJson('/api/v1/accounting/period-management/fiscal-years', [
            'code' => 'FY2036',
            'name' => 'Fiscal Year 2037',
            'start_date' => '2037-01-01',
            'end_date' => '2037-12-31',
            'is_current' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_fiscal_year_reads_are_scoped_to_the_current_company_context(): void
    {
        $userA = $this->actingAsUserWithAbilities(['period.read']);
        $tenantA = $this->createTenant($userA, 'company-a-2038');
        $headersA = $this->tenantHeaders($tenantA);
        $otherOwner = User::factory()->create();
        $tenantB = $this->createTenant($otherOwner, 'company-b-2038');

        $tenantAFiscalYear = FiscalYear::query()->create([
            'tenant_id' => $tenantA->id,
            'company_id' => $tenantA->id,
            'code' => 'FY2038-A',
            'name' => 'Tenant A Fiscal Year',
            'start_date' => '2038-01-01',
            'end_date' => '2038-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);

        FiscalYear::query()->create([
            'tenant_id' => $tenantB->id,
            'company_id' => $tenantB->id,
            'code' => 'FY2038-B',
            'name' => 'Tenant B Fiscal Year',
            'start_date' => '2038-01-01',
            'end_date' => '2038-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);

        $this->withHeaders($headersA)
            ->getJson('/api/v1/accounting/period-management/fiscal-years?is_current=true&pageSize=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', (string)$tenantAFiscalYear->id);

        $this->withHeaders($headersA)
            ->getJson('/api/v1/accounting/period-management/fiscal-years/current')
            ->assertOk()
            ->assertJsonPath('id', (string)$tenantAFiscalYear->id);

        $this->withHeaders($headersA)
            ->getJson('/api/v1/accounting/period-management/kpi')
            ->assertOk()
            ->assertJsonPath('data.current_fiscal_year.id', (string)$tenantAFiscalYear->id);
    }

    public function test_cannot_update_another_companys_fiscal_year_by_id(): void
    {
        $userA = $this->actingAsUserWithAbilities(['period.write']);
        $tenantA = $this->createTenant($userA, 'company-a-2039');
        $headersA = $this->tenantHeaders($tenantA);
        $otherOwner = User::factory()->create();
        $tenantB = $this->createTenant($otherOwner, 'company-b-2039');

        FiscalYear::query()->create([
            'tenant_id' => $tenantA->id,
            'company_id' => $tenantA->id,
            'code' => 'FY2039-A',
            'name' => 'Tenant A Fiscal Year',
            'start_date' => '2039-01-01',
            'end_date' => '2039-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);

        $tenantBFiscalYear = FiscalYear::query()->create([
            'tenant_id' => $tenantB->id,
            'company_id' => $tenantB->id,
            'code' => 'FY2039-B',
            'name' => 'Tenant B Fiscal Year',
            'start_date' => '2039-01-01',
            'end_date' => '2039-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);

        $response = $this->withHeaders($headersA)
            ->patchJson("/api/v1/accounting/period-management/fiscal-years/{$tenantBFiscalYear->id}", [
                'code' => 'FY2039-B-REV',
                'name' => 'Tenant B Revised',
                'status' => 'open',
            ]);

        $response->assertNotFound();

        $this->assertDatabaseHas('fiscal_years', [
            'id' => $tenantBFiscalYear->id,
            'tenant_id' => $tenantB->id,
            'code' => 'FY2039-B',
        ]);
    }

    /**
     * @return array{0: Tenant, 1: array<string, string>}
     */
    private function actingAsUserWithAbilities(array $abilities): User
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, $abilities, 'sanctum');

        return $user;
    }

    private function createTenant(User $user, string $slug): Tenant
    {
        return Tenant::create([
            'name' => 'Fiscal Scope '.strtoupper($slug),
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function tenantHeaders(Tenant $tenant): array
    {
        return [
            'X-Tenant-ID' => (string)$tenant->id,
        ];
    }
}

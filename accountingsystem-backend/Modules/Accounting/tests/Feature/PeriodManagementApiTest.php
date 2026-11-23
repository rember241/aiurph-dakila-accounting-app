<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Database\Seeders\FiscalYearPeriodSeeder;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Users\Models\Tenant;
use Tests\TestCase;

class PeriodManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->actingAsCompanyUser(['period.read', 'period.write']);
    }

    public function test_can_create_fiscal_year_and_generate_periods(): void
    {
        $payload = [
            'code' => 'FY2026',
            'name' => 'Fiscal Year 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ];

        $res = $this->postJson('/api/v1/accounting/period-management/fiscal-years', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('code', 'FY2026')
            ->assertJsonPath('is_current', true)
            ->assertJsonPath('status', 'open')
            ->assertJsonCount(12, 'periods');

        $this->assertDatabaseHas('fiscal_years', [
            'code' => 'FY2026',
            'name' => 'Fiscal Year 2026',
            'is_current' => true,
            'status' => 'open',
        ]);

        $this->assertDatabaseCount('accounting_periods', 12);
        $this->assertDatabaseCount('fiscal_years', 1);
    }

    public function test_non_current_fiscal_year_is_created_as_closed(): void
    {
        $payload = [
            'code' => 'FY2025',
            'name' => 'Fiscal Year 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => false,
        ];

        $res = $this->postJson('/api/v1/accounting/period-management/fiscal-years', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('code', 'FY2025')
            ->assertJsonPath('is_current', false)
            ->assertJsonPath('status', 'closed');

        $this->assertDatabaseHas('fiscal_years', [
            'code' => 'FY2025',
            'is_current' => false,
            'status' => 'closed',
        ]);

        $this->assertSame(
            12,
            AccountingPeriod::query()
                ->where('fiscal_year_id', (string)$res->json('id'))
                ->where('status', 'open')
                ->count()
        );
    }

    public function test_can_set_current_fiscal_year(): void
    {
        $oldCurrent = $this->createFiscalYear([
            'code' => 'FY2025',
            'name' => 'Fiscal Year 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => true,
        ]);

        $target = $this->createFiscalYear([
            'code' => 'FY2026',
            'name' => 'Fiscal Year 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => false,
        ]);

        $oldCurrentPeriod = AccountingPeriod::query()->create([
            'fiscal_year_id' => $oldCurrent->id,
            'period_number' => 1,
            'name' => 'Jan 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);

        $res = $this->postJson("/api/v1/accounting/period-management/fiscal-years/{$target->id}/set-current");

        $res->assertOk()
            ->assertJsonPath('id', $target->id)
            ->assertJsonPath('is_current', true)
            ->assertJsonPath('status', 'open');

        $oldCurrent->refresh();
        $target->refresh();
        $oldCurrentPeriod->refresh();

        $this->assertFalse($oldCurrent->is_current);
        $this->assertSame('closed', $oldCurrent->status->value);
        $this->assertTrue($target->is_current);
        $this->assertSame('open', $target->status->value);
        $this->assertSame('closed', $oldCurrentPeriod->status->value);
        $this->assertTrue($oldCurrentPeriod->lock_ap);
        $this->assertTrue($oldCurrentPeriod->lock_ar);
        $this->assertTrue($oldCurrentPeriod->lock_inventory);
        $this->assertNotNull($oldCurrentPeriod->closed_at);
        $this->assertDatabaseCount('fiscal_years', 2);
        $this->assertSame(
            1,
            FiscalYear::query()->where('is_current', true)->count()
        );
        $this->assertSame(
            1,
            FiscalYear::query()->where('status', 'open')->count()
        );
    }

    public function test_can_delete_fiscal_year_and_cascade_periods(): void
    {
        $payload = [
            'code' => 'FY2033',
            'name' => 'Fiscal Year 2033',
            'start_date' => '2033-01-01',
            'end_date' => '2033-12-31',
            'is_current' => false,
        ];

        $create = $this->postJson('/api/v1/accounting/period-management/fiscal-years', $payload);
        $create->assertStatus(201);

        $fiscalYearId = (string)$create->json('id');

        $this->assertDatabaseHas('fiscal_years', ['id' => $fiscalYearId]);
        $this->assertSame(12, AccountingPeriod::query()->where('fiscal_year_id', $fiscalYearId)->count());

        $delete = $this->deleteJson("/api/v1/accounting/period-management/fiscal-years/{$fiscalYearId}");
        $delete->assertOk()->assertJsonPath('message', 'Fiscal year deleted successfully');

        $this->assertDatabaseMissing('fiscal_years', ['id' => $fiscalYearId]);
        $this->assertSame(0, AccountingPeriod::query()->where('fiscal_year_id', $fiscalYearId)->count());
    }

    public function test_can_update_fiscal_year_code_and_name_without_changing_status(): void
    {
        $fiscalYear = $this->createFiscalYear([
            'code' => 'FY2028',
            'name' => 'Fiscal Year 2028',
            'start_date' => '2028-01-01',
            'end_date' => '2028-12-31',
            'is_current' => true,
            'status' => 'open',
        ]);

        AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 1,
            'name' => 'Jan 2028',
            'start_date' => '2028-01-01',
            'end_date' => '2028-01-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);

        $res = $this->patchJson("/api/v1/accounting/period-management/fiscal-years/{$fiscalYear->id}", [
            'code' => 'FY2028-REV',
            'name' => 'FY 2028 Revised',
            'status' => 'open',
        ]);

        $res->assertOk()
            ->assertJsonPath('data.code', 'FY2028-REV')
            ->assertJsonPath('data.name', 'FY 2028 Revised')
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('fiscal_years', [
            'id' => $fiscalYear->id,
            'code' => 'FY2028-REV',
            'name' => 'FY 2028 Revised',
            'status' => 'open',
        ]);
    }

    public function test_cannot_change_fiscal_year_status_when_any_period_is_not_closed(): void
    {
        $fiscalYear = $this->createFiscalYear([
            'code' => 'FY2029',
            'name' => 'Fiscal Year 2029',
            'start_date' => '2029-01-01',
            'end_date' => '2029-12-31',
            'is_current' => true,
            'status' => 'open',
        ]);

        AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 1,
            'name' => 'Jan 2029',
            'start_date' => '2029-01-01',
            'end_date' => '2029-01-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);

        $res = $this->patchJson("/api/v1/accounting/period-management/fiscal-years/{$fiscalYear->id}", [
            'code' => 'FY2029',
            'name' => 'Fiscal Year 2029',
            'status' => 'closed',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $fiscalYear->refresh();
        $this->assertSame('open', $fiscalYear->status->value);
        $this->assertTrue($fiscalYear->is_current);
    }

    public function test_can_change_fiscal_year_status_when_all_periods_are_closed(): void
    {
        $fiscalYear = $this->createFiscalYear([
            'code' => 'FY2030',
            'name' => 'Fiscal Year 2030',
            'start_date' => '2030-01-01',
            'end_date' => '2030-12-31',
            'is_current' => true,
            'status' => 'open',
        ]);

        AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 1,
            'name' => 'Jan 2030',
            'start_date' => '2030-01-01',
            'end_date' => '2030-01-31',
            'status' => 'closed',
            'lock_ap' => true,
            'lock_ar' => true,
            'lock_inventory' => true,
            'closed_at' => now(),
        ]);

        $res = $this->patchJson("/api/v1/accounting/period-management/fiscal-years/{$fiscalYear->id}", [
            'code' => 'FY2030',
            'name' => 'Fiscal Year 2030',
            'status' => 'closed',
        ]);

        $res->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.is_current', false);

        $fiscalYear->refresh();
        $this->assertSame('closed', $fiscalYear->status->value);
        $this->assertFalse($fiscalYear->is_current);
        $this->assertNotNull($fiscalYear->closed_at);
    }

    public function test_can_reopen_closed_fiscal_year_without_changing_period_states(): void
    {
        $fiscalYear = $this->createFiscalYear([
            'code' => 'FY2031',
            'name' => 'Fiscal Year 2031',
            'start_date' => '2031-01-01',
            'end_date' => '2031-12-31',
            'is_current' => false,
            'status' => 'closed',
        ]);

        $openPeriod = AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 1,
            'name' => 'Jan 2031',
            'start_date' => '2031-01-01',
            'end_date' => '2031-01-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);

        $closedPeriod = AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 2,
            'name' => 'Feb 2031',
            'start_date' => '2031-02-01',
            'end_date' => '2031-02-28',
            'status' => 'closed',
            'lock_ap' => true,
            'lock_ar' => true,
            'lock_inventory' => true,
            'closed_at' => now(),
        ]);

        $res = $this->patchJson("/api/v1/accounting/period-management/fiscal-years/{$fiscalYear->id}", [
            'code' => 'FY2031',
            'name' => 'Fiscal Year 2031 Reopened',
            'status' => 'open',
        ]);

        $res->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.name', 'Fiscal Year 2031 Reopened');

        $fiscalYear->refresh();
        $openPeriod->refresh();
        $closedPeriod->refresh();

        $this->assertSame('open', $fiscalYear->status->value);
        $this->assertNull($fiscalYear->closed_at);

        $this->assertSame('open', $openPeriod->status->value);
        $this->assertFalse($openPeriod->lock_ap);
        $this->assertFalse($openPeriod->lock_ar);
        $this->assertFalse($openPeriod->lock_inventory);

        $this->assertSame('closed', $closedPeriod->status->value);
        $this->assertTrue($closedPeriod->lock_ap);
        $this->assertTrue($closedPeriod->lock_ar);
        $this->assertTrue($closedPeriod->lock_inventory);
    }

    public function test_closed_fiscal_year_blocks_posting_even_if_period_is_open(): void
    {
        $fiscalYear = $this->createFiscalYear([
            'code' => 'FY2032',
            'name' => 'Fiscal Year 2032',
            'start_date' => '2032-01-01',
            'end_date' => '2032-12-31',
            'is_current' => false,
            'status' => 'closed',
        ]);

        $period = AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 1,
            'name' => 'Jan 2032',
            'start_date' => '2032-01-01',
            'end_date' => '2032-01-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);

        $this->assertFalse($period->canAcceptPosting('ap'));
        $this->assertFalse($period->canAcceptPosting('ar'));
        $this->assertFalse($period->canAcceptPosting('inventory'));
        $this->assertFalse($period->canAcceptPosting('gl'));
    }

    public function test_fiscal_year_period_seeder_creates_exactly_20_fiscal_years(): void
    {
        if (!class_exists(FiscalYearPeriodSeeder::class)) {
            $this->markTestSkipped('FiscalYearPeriodSeeder class is not available in this branch.');
        }

        $legacyFiscalYear = $this->createFiscalYear([
            'code' => 'FY-LEGACY',
            'name' => 'Fiscal Year Legacy',
            'start_date' => '2000-01-01',
            'end_date' => '2000-12-31',
            'is_current' => false,
        ]);

        AccountingPeriod::query()->create([
            'fiscal_year_id' => $legacyFiscalYear->id,
            'period_number' => 1,
            'name' => 'Jan 2000',
            'start_date' => '2000-01-01',
            'end_date' => '2000-01-31',
            'status' => 'closed',
            'lock_ap' => true,
            'lock_ar' => true,
            'lock_inventory' => true,
            'closed_at' => now(),
        ]);

        $this->seed(FiscalYearPeriodSeeder::class);

        $this->assertDatabaseCount('fiscal_years', 20);
        $this->assertDatabaseCount('accounting_periods', 240);
        $this->assertDatabaseMissing('fiscal_years', ['code' => 'FY-LEGACY']);
        $this->assertSame(1, FiscalYear::query()->where('is_current', true)->count());
        $this->assertSame(1, FiscalYear::query()->where('status', 'open')->count());
    }

    public function test_can_list_fiscal_years_filtered_by_is_current(): void
    {
        $current = $this->createFiscalYear([
            'code' => 'FY2026',
            'name' => 'Fiscal Year 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);

        $this->createFiscalYear([
            'code' => 'FY2025',
            'name' => 'Fiscal Year 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => false,
        ]);

        $res = $this->getJson('/api/v1/accounting/period-management/fiscal-years?is_current=true&pageSize=10');

        $res->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $current->id);
    }

    public function test_can_list_fiscal_years_filtered_by_search(): void
    {
        $match = $this->createFiscalYear([
            'code' => 'FY2026',
            'name' => 'Fiscal Year 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);

        $this->createFiscalYear([
            'code' => 'FY2025',
            'name' => 'Fiscal Year 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => false,
        ]);

        $res = $this->getJson('/api/v1/accounting/period-management/fiscal-years?search=2026&pageSize=10');

        $res->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_can_transition_period_status(): void
    {
        $fiscalYear = $this->createFiscalYear([
            'code' => 'FY2026',
            'name' => 'Fiscal Year 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);

        $period = AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 1,
            'name' => 'Jan 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        $softClose = $this->patchJson("/api/v1/accounting/period-management/periods/{$period->id}/status", [
            'status' => 'soft_closed',
        ]);

        $softClose->assertOk()
            ->assertJsonPath('data.status', 'soft_closed')
            ->assertJsonPath('data.lock_ap', true)
            ->assertJsonPath('data.lock_ar', true)
            ->assertJsonPath('data.lock_inventory', true);

        $close = $this->patchJson("/api/v1/accounting/period-management/periods/{$period->id}/status", [
            'status' => 'closed',
        ]);

        $close->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $fiscalYear->refresh();
        $this->assertSame('closed', $fiscalYear->status->value);
        $this->assertFalse($fiscalYear->is_current);
        $this->assertNotNull($fiscalYear->closed_at);

        $invalid = $this->patchJson("/api/v1/accounting/period-management/periods/{$period->id}/status", [
            'status' => 'open',
        ]);

        $invalid->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_can_list_periods_filtered_by_fiscal_year_id(): void
    {
        $fiscalYearA = $this->createFiscalYear([
            'code' => 'FY2026',
            'name' => 'Fiscal Year 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);

        $fiscalYearB = $this->createFiscalYear([
            'code' => 'FY2027',
            'name' => 'Fiscal Year 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_current' => false,
        ]);

        AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYearA->id,
            'period_number' => 1,
            'name' => 'Jan 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYearB->id,
            'period_number' => 1,
            'name' => 'Jan 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-31',
            'status' => 'open',
        ]);

        $res = $this->getJson("/api/v1/accounting/period-management/periods?fiscal_year_id={$fiscalYearA->id}&pageSize=10");

        $res->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.fiscal_year_id', $fiscalYearA->id);
    }

    public function test_can_list_periods_filtered_by_search(): void
    {
        $fiscalYearA = $this->createFiscalYear([
            'code' => 'FY2026',
            'name' => 'Fiscal Year 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);

        $fiscalYearB = $this->createFiscalYear([
            'code' => 'FY2027',
            'name' => 'Fiscal Year 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_current' => false,
        ]);

        $periodA = AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYearA->id,
            'period_number' => 2,
            'name' => 'Feb 2026',
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'status' => 'open',
        ]);

        AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYearB->id,
            'period_number' => 3,
            'name' => 'Mar 2027',
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-31',
            'status' => 'open',
        ]);

        $res = $this->getJson('/api/v1/accounting/period-management/periods?search=Feb%202026&pageSize=10');

        $res->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $periodA->id);
    }

    public function test_can_get_period_management_kpi(): void
    {
        $currentFiscalYear = $this->createFiscalYear([
            'code' => 'FY2099',
            'name' => 'Fiscal Year 2099',
            'start_date' => '2099-01-01',
            'end_date' => '2099-12-31',
            'is_current' => true,
        ]);

        for ($periodNumber = 1; $periodNumber <= 12; $periodNumber++) {
            AccountingPeriod::query()->create([
                'fiscal_year_id' => $currentFiscalYear->id,
                'period_number' => $periodNumber,
                'name' => Carbon::create(2099, $periodNumber, 1)->format('M Y'),
                'start_date' => Carbon::create(2099, $periodNumber, 1)->startOfMonth()->toDateString(),
                'end_date' => Carbon::create(2099, $periodNumber, 1)->endOfMonth()->toDateString(),
                'status' => $periodNumber <= 4 ? 'closed' : ($periodNumber === 5 ? 'soft_closed' : 'open'),
                'lock_ap' => $periodNumber <= 5,
                'lock_ar' => $periodNumber <= 5,
                'lock_inventory' => $periodNumber <= 5,
            ]);
        }

        $res = $this->getJson('/api/v1/accounting/period-management/kpi');
        $expectedDaysLeft = (int)max(0, Carbon::today()->diffInDays(Carbon::parse('2099-12-31'), false));

        $res->assertOk()
            ->assertJsonPath('data.current_fiscal_year.id', $currentFiscalYear->id)
            ->assertJsonPath('data.days_left_in_current_year', $expectedDaysLeft)
            ->assertJsonPath('data.closing_progress.closed_periods', 5)
            ->assertJsonPath('data.closing_progress.total_periods', 12)
            ->assertJsonPath('data.closing_progress.percentage', 41.67);
    }

    public function test_cannot_transition_period_to_non_closed_when_fiscal_year_is_closed(): void
    {
        $fiscalYear = $this->createFiscalYear([
            'code' => 'FY2024',
            'name' => 'Fiscal Year 2024',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'is_current' => false,
            'status' => 'closed',
        ]);

        $period = AccountingPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 1,
            'name' => 'Jan 2024',
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
            'status' => 'closed',
            'lock_ap' => true,
            'lock_ar' => true,
            'lock_inventory' => true,
            'closed_at' => now(),
        ]);

        $res = $this->patchJson("/api/v1/accounting/period-management/periods/{$period->id}/status", [
            'status' => 'open',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    private function createFiscalYear(array $attributes): FiscalYear
    {
        $isCurrent = (bool)$attributes['is_current'];
        $status = $attributes['status'] ?? ($isCurrent ? 'open' : 'closed');

        return FiscalYear::query()->create([
            'tenant_id' => $this->company->id,
            'company_id' => $this->company->id,
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'start_date' => $attributes['start_date'],
            'end_date' => $attributes['end_date'],
            'status' => $status,
            'is_current' => $isCurrent,
        ]);
    }

    /**
     * @param array<int, string> $abilities
     */
    private function actingAsCompanyUser(array $abilities): Tenant
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, $abilities, 'sanctum');

        return Tenant::create([
            'name' => 'Period Management Company '.$user->id,
            'slug' => 'period-management-company-'.$user->id,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);
    }
}

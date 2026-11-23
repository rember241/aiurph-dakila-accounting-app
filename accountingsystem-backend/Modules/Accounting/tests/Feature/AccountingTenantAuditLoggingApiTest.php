<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Users\Models\Tenant;
use Tests\TestCase;

class AccountingTenantAuditLoggingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_create_audit_includes_tenant_id_in_shared_audit_log(): void
    {
        [$tenant, $headers] = $this->actingAsTenantUser(['accounting.write']);

        $accountTypeId = DB::table('account_types')->insertGetId([
            'code' => 'current-assets',
            'name' => 'Current Assets',
            'core_type' => 'asset',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $detailTypeId = DB::table('detail_types')->insertGetId([
            'account_type_id' => $accountTypeId,
            'code' => 'cash-in-hand',
            'name' => 'Cash In Hand',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeaders($headers)->postJson('/api/v1/accounts', [
            'name' => 'Main Cash',
            'type' => 'asset',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
            'status' => 'active',
            'is_posting' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Account created');

        $audit = AuditLog::query()
            ->where('action', 'account.created')
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $this->assertSame((string)$tenant->id, (string)$audit->tenant_id);
        $this->assertSame('account.created', $audit->action);
    }

    public function test_journal_entry_create_audit_includes_tenant_id_in_shared_audit_log(): void
    {
        [$tenant, $headers] = $this->actingAsTenantUser(['journal.write']);

        $debitAccount = Account::factory()->create([
            'tenant_id' => $tenant->id,
            'normal_balance' => 'debit',
            'type' => 'asset',
            'is_posting' => true,
            'status' => 'active',
        ]);

        $creditAccount = Account::factory()->create([
            'tenant_id' => $tenant->id,
            'normal_balance' => 'credit',
            'type' => 'liability',
            'is_posting' => true,
            'status' => 'active',
        ]);

        $fiscalYear = FiscalYear::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'FY2040',
            'name' => 'Fiscal Year 2040',
            'start_date' => '2040-01-01',
            'end_date' => '2040-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);

        AccountingPeriod::query()->create([
            'tenant_id' => $tenant->id,
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 1,
            'name' => 'Jan 2040',
            'start_date' => '2040-01-01',
            'end_date' => '2040-01-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);

        $response = $this->withHeaders($headers)->postJson('/api/v1/journal-entries', [
            'entryNumber' => 'JE-AUDIT-1',
            'date' => '2040-01-15',
            'type' => 'general',
            'referenceNumber' => 'REF-AUDIT-1',
            'note' => 'Tenant-scoped audit verification',
            'status' => 'posted',
            'journalItems' => [
                [
                    'accountId' => $debitAccount->id,
                    'description' => 'Debit line',
                    'debit' => 250,
                    'credit' => 0,
                ],
                [
                    'accountId' => $creditAccount->id,
                    'description' => 'Credit line',
                    'debit' => 0,
                    'credit' => 250,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Journal Entry #JE-AUDIT-1 created');

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $tenant->id,
            'entry_number' => 'JE-AUDIT-1',
        ]);

        $audit = AuditLog::query()
            ->where('action', 'journal_entry.created')
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $this->assertSame('JE-AUDIT-1', $audit->after['entry_number'] ?? null);
        $this->assertSame(2, $audit->after['journal_item_count'] ?? null);
    }

    public function test_fiscal_year_write_audits_include_tenant_id_in_shared_audit_log(): void
    {
        [$tenant, $headers] = $this->actingAsTenantUser(['period.write']);

        $createResponse = $this->withHeaders($headers)->postJson('/api/v1/accounting/period-management/fiscal-years', [
            'code' => 'FY2050',
            'name' => 'Fiscal Year 2050',
            'start_date' => '2050-01-01',
            'end_date' => '2050-12-31',
            'is_current' => true,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('code', 'FY2050');

        $createdFiscalYearId = (string)$createResponse->json('id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'fiscal_year.created',
            'tenant_id' => $tenant->id,
            'auditable_id' => $createdFiscalYearId,
        ]);

        $targetFiscalYear = FiscalYear::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'FY2051',
            'name' => 'Fiscal Year 2051',
            'start_date' => '2051-01-01',
            'end_date' => '2051-12-31',
            'status' => 'closed',
            'is_current' => false,
        ]);

        $setCurrentResponse = $this->withHeaders($headers)
            ->postJson("/api/v1/accounting/period-management/fiscal-years/{$targetFiscalYear->id}/set-current");
        $setCurrentResponse->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'fiscal_year.set_current',
            'tenant_id' => $tenant->id,
            'auditable_id' => $targetFiscalYear->id,
        ]);

        $updateResponse = $this->withHeaders($headers)
            ->patchJson("/api/v1/accounting/period-management/fiscal-years/{$targetFiscalYear->id}", [
                'code' => 'FY2051-REV',
                'name' => 'Fiscal Year 2051 Revised',
                'status' => 'open',
            ]);
        $updateResponse->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'fiscal_year.updated',
            'tenant_id' => $tenant->id,
            'auditable_id' => $targetFiscalYear->id,
        ]);

        $deleteResponse = $this->withHeaders($headers)
            ->deleteJson("/api/v1/accounting/period-management/fiscal-years/{$targetFiscalYear->id}");
        $deleteResponse->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'fiscal_year.deleted',
            'tenant_id' => $tenant->id,
            'auditable_id' => $targetFiscalYear->id,
        ]);
    }

    public function test_period_transition_audits_include_tenant_id_and_auto_close_event(): void
    {
        [$tenant, $headers] = $this->actingAsTenantUser(['period.write']);

        $fiscalYear = FiscalYear::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'FY2060',
            'name' => 'Fiscal Year 2060',
            'start_date' => '2060-01-01',
            'end_date' => '2060-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);

        $period = AccountingPeriod::query()->create([
            'tenant_id' => $tenant->id,
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 1,
            'name' => 'Jan 2060',
            'start_date' => '2060-01-01',
            'end_date' => '2060-01-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);

        $response = $this->withHeaders($headers)
            ->patchJson("/api/v1/accounting/period-management/periods/{$period->id}/status", [
                'status' => 'closed',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'accounting_period.status_transitioned',
            'tenant_id' => $tenant->id,
            'auditable_id' => $period->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'fiscal_year.auto_closed',
            'tenant_id' => $tenant->id,
            'auditable_id' => $fiscalYear->id,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: array<string, string>}
     */
    private function actingAsTenantUser(array $abilities): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'name' => 'Audit Tenant',
            'slug' => 'audit-tenant-'.$user->id,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user, $abilities, 'sanctum');

        return [
            $tenant,
            [
                'X-Tenant-ID' => (string)$tenant->id,
            ],
        ];
    }
}

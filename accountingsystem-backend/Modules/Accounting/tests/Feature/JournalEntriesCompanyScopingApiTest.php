<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Models\Tenant;
use Tests\TestCase;

class JournalEntriesCompanyScopingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_entry_creation_persists_company_and_org_unit_scope(): void
    {
        [$user, $company, $headers] = $this->actingAsCompanyOwner('journal-company-a');

        $rootOrgUnit = OrgUnit::create([
            'company_id' => $company->getKey(),
            'type' => 'head_office',
            'name' => 'Head Office',
            'status' => 'active',
            'is_root' => true,
        ]);

        $debitAccount = Account::factory()->create([
            'company_id' => $company->getKey(),
            'tenant_id' => $company->getKey(),
            'normal_balance' => 'debit',
            'type' => 'asset',
            'is_posting' => true,
            'status' => 'active',
        ]);

        $creditAccount = Account::factory()->create([
            'company_id' => $company->getKey(),
            'tenant_id' => $company->getKey(),
            'normal_balance' => 'credit',
            'type' => 'liability',
            'is_posting' => true,
            'status' => 'active',
        ]);

        $fiscalYear = FiscalYear::query()->create([
            'company_id' => $company->getKey(),
            'tenant_id' => $company->getKey(),
            'code' => 'FY2045',
            'name' => 'Fiscal Year 2045',
            'start_date' => '2045-01-01',
            'end_date' => '2045-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);

        AccountingPeriod::query()->create([
            'company_id' => $company->getKey(),
            'tenant_id' => $company->getKey(),
            'fiscal_year_id' => $fiscalYear->getKey(),
            'period_number' => 1,
            'name' => 'Jan 2045',
            'start_date' => '2045-01-01',
            'end_date' => '2045-01-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);

        $response = $this->withHeaders($headers)->postJson('/api/v1/journal-entries', [
            'entryNumber' => 'JE-COMPANY-1',
            'date' => '2045-01-10',
            'type' => 'general',
            'referenceNumber' => 'REF-COMPANY-1',
            'note' => 'Company scoped journal entry',
            'status' => 'posted',
            'org_unit_id' => (string) $rootOrgUnit->getKey(),
            'journalItems' => [
                [
                    'accountId' => $debitAccount->getKey(),
                    'description' => 'Debit line',
                    'debit' => 100,
                    'credit' => 0,
                ],
                [
                    'accountId' => $creditAccount->getKey(),
                    'description' => 'Credit line',
                    'debit' => 0,
                    'credit' => 100,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.company_id', (string) $company->getKey())
            ->assertJsonPath('data.org_unit_id', (string) $rootOrgUnit->getKey());

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $company->getKey(),
            'org_unit_id' => $rootOrgUnit->getKey(),
            'entry_number' => 'JE-COMPANY-1',
        ]);
    }

    public function test_journal_entry_list_is_scoped_to_the_current_company(): void
    {
        [$user, $companyA, $headers] = $this->actingAsCompanyOwner('journal-list-a');
        $otherOwner = User::factory()->create();
        $companyB = Tenant::create([
            'name' => 'Journal List B',
            'slug' => 'journal-list-b',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $otherOwner->getKey(),
        ]);

        $entryA = JournalEntry::query()->create([
            'company_id' => $companyA->getKey(),
            'entry_date' => '2046-01-15',
            'entry_number' => 'JE-A',
            'reference_number' => 'REF-A',
            'type' => 'general',
            'status' => 'draft',
            'notes' => 'Visible entry',
            'amount' => 500,
        ]);

        JournalEntry::query()->create([
            'company_id' => $companyB->getKey(),
            'entry_date' => '2046-01-16',
            'entry_number' => 'JE-B',
            'reference_number' => 'REF-B',
            'type' => 'general',
            'status' => 'draft',
            'notes' => 'Hidden entry',
            'amount' => 600,
        ]);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/journal-entries?search=JE&per_page=5');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $entryA->getKey())
            ->assertJsonPath('data.0.companyId', (string) $companyA->getKey());
    }

    /**
     * @return array{0: User, 1: Tenant, 2: array<string, string>}
     */
    private function actingAsCompanyOwner(string $slug): array
    {
        $user = User::factory()->create([
            'company_id' => null,
        ]);

        $company = Tenant::create([
            'name' => 'Company '.Str::upper($slug),
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->getKey(),
        ]);

        $user->forceFill(['company_id' => $company->getKey()])->save();

        Sanctum::actingAs($user, [], 'sanctum');

        return [$user, $company, [
            'X-Company-ID' => (string) $company->getKey(),
        ]];
    }
}

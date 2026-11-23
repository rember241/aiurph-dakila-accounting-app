<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Users\Models\Company;
use Tests\TestCase;

class AccountingDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_posted_company_entries_and_validates_dates(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'Books', 'slug' => 'books', 'status' => 'active', 'timezone' => 'UTC', 'owner_user_id' => $owner->id]);
        Sanctum::actingAs($owner, [], 'sanctum');
        $fund = FundSource::create(['company_id' => $company->id, 'name' => 'General', 'status' => 'active']);
        $cash = Account::factory()->create(['company_id' => $company->id, 'tenant_id' => $company->id, 'type' => 'asset', 'normal_balance' => 'debit', 'is_posting' => true, 'is_contra' => false]);
        $revenue = Account::factory()->create(['company_id' => $company->id, 'tenant_id' => $company->id, 'type' => 'revenue', 'normal_balance' => 'credit', 'is_posting' => true, 'is_contra' => false]);
        foreach (['posted', 'draft'] as $index => $status) {
            $entry = JournalEntry::create(['company_id' => $company->id, 'prefix' => 'JE', 'entry_number' => $index + 1, 'entry_date' => '2026-10-10', 'type' => 'general', 'fund_source_id' => $fund->id, 'amount' => 125, 'status' => $status]);
            foreach ([[$cash, 'debit'], [$revenue, 'credit']] as [$account, $type]) {
                JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $account->id, 'description' => 'Test entry', 'type' => $type, 'amount' => 125]);
            }
        }
        $response = $this->getJson('/api/v1/accounting/dashboard?startDate=2026-10-01&date=2026-10-23')->assertOk();
        self::assertSame(125.0, (float) $response->json('income.totalRevenue.0'));
        self::assertSame(125.0, (float) $response->json('income.netTotal.0'));
        self::assertSame(125.0, (float) $response->json('balance.totalAssets.0'));
        $this->getJson('/api/v1/accounting/dashboard?startDate=2026-11-01&date=2026-10-23')->assertUnprocessable();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/accounting/dashboard?startDate=2026-10-01&date=2026-10-23')->assertUnauthorized();
    }
}

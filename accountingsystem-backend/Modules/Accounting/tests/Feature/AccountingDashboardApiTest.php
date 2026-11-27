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
        $this->getJson('/api/v1/accounting/dashboard?startDate=2026-10-01&date=2026-10-23&summaryOnly=1')
            ->assertOk()
            ->assertJsonPath('months.5.cashBalance', null)
            ->assertJsonPath('months.5.totalIncome', '125.00');
        $this->getJson('/api/v1/accounting/dashboard?startDate=2026-11-01&date=2026-10-23')->assertUnprocessable();
    }

    public function test_dashboard_summarizes_monthly_activity_and_carries_balances_forward(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'Trend Books', 'slug' => 'trend-books', 'status' => 'active', 'timezone' => 'UTC', 'owner_user_id' => $owner->id]);
        Sanctum::actingAs($owner, [], 'sanctum');
        $fund = FundSource::create(['company_id' => $company->id, 'name' => 'General', 'status' => 'active']);
        $accounts = Account::factory()->state([
            'company_id' => $company->id, 'tenant_id' => $company->id, 'is_posting' => true, 'is_contra' => false,
        ]);
        $bankGroup = $accounts->create(['name' => 'Bank Accounts', 'type' => 'asset', 'is_posting' => false]);
        $cash = $accounts->create(['name' => 'Operating Bank', 'type' => 'asset', 'parent_id' => $bankGroup->id]);
        $receivables = $accounts->create(['name' => 'Accounts Receivable', 'type' => 'asset']);
        $payables = $accounts->create(['name' => 'Accounts Payable', 'type' => 'liability']);
        $income = $accounts->create(['type' => 'revenue']);
        $expense = $accounts->create(['type' => 'expense']);

        $transactions = [
            ['2026-03-20', 'posted', $cash, $income, 100],
            ['2026-09-11', 'posted', $receivables, $income, 70],
            ['2026-10-02', 'posted', $expense, $payables, 25],
            ['2026-10-15', 'posted', $cash, $receivables, 40],
            ['2026-10-16', 'void', $cash, $income, 90],
            ['2026-10-24', 'posted', $expense, $cash, 80],
        ];

        foreach ($transactions as $index => [$date, $status, $debit, $credit, $amount]) {
            $entry = JournalEntry::create([
                'company_id' => $company->id, 'prefix' => 'JE', 'entry_number' => $index + 1,
                'entry_date' => $date, 'type' => 'general', 'fund_source_id' => $fund->id,
                'amount' => $amount, 'status' => $status,
            ]);

            foreach (['debit' => $debit, 'credit' => $credit] as $type => $account) {
                JournalItem::create([
                    'journal_entry_id' => $entry->id, 'account_id' => $account->id,
                    'description' => 'Test entry', 'type' => $type, 'amount' => $amount,
                ]);
            }
        }

        $this->getJson('/api/v1/accounting/dashboard?startDate=2026-10-01&date=2026-10-23&summaryOnly=1')
            ->assertOk()
            ->assertJsonCount(6, 'months')
            ->assertJsonMissingPath('income')
            ->assertJsonPath('months.0.month', '2026-05')
            ->assertJsonPath('months.0.cashBalance', '100.00')
            ->assertJsonPath('months.0.totalIncome', '0.00')
            ->assertJsonPath('months.4.receivables', '70.00')
            ->assertJsonPath('months.4.netProfit', '70.00')
            ->assertJsonPath('months.5.month', '2026-10')
            ->assertJsonPath('months.5.cashBalance', '140.00')
            ->assertJsonPath('months.5.receivables', '30.00')
            ->assertJsonPath('rankings.totalExpenses.0.id', $expense->id)
            ->assertJsonPath('rankings.totalExpenses.0.amount', '25.00')
            ->assertJsonCount(0, 'rankings.totalIncome')
            ->assertJsonPath('months.5.payables', '25.00')
            ->assertJsonPath('months.5.totalExpenses', '25.00')
            ->assertJsonPath('months.5.netProfit', '-25.00');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/accounting/dashboard?startDate=2026-10-01&date=2026-10-23')->assertUnauthorized();
    }
}

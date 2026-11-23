<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Users\Models\Company;
use Tests\TestCase;

class CashFlowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_flow_report_computes_posted_cash_movement_and_previous_period_comparison(): void
    {
        [$company, $headers] = $this->actingAsCompanyOwner();
        $fundSource = $this->fundSource($company);
        $currentAssetsTypeId = $this->accountType('current-assets', 'Current Assets', 'asset');
        $fixedAssetsTypeId = $this->accountType('fixed-assets', 'Fixed Assets', 'asset');
        $currentLiabilitiesTypeId = $this->accountType('current-liabilities', 'Current Liabilities', 'liability');
        $cashDetailTypeId = $this->detailType($currentAssetsTypeId, 'cash-in-hand', 'Cash In Hand');
        $arDetailTypeId = $this->detailType($currentAssetsTypeId, 'accounts-receivable', 'Accounts Receivable');
        $fixedAssetDetailTypeId = $this->detailType($fixedAssetsTypeId, 'office-equipment', 'Office Equipment');
        $loanDetailTypeId = $this->detailType($currentLiabilitiesTypeId, 'loans-liabilities', 'Loans (Liabilities)');

        $cashGroup = $this->account($company, '1000', 'Cash In Hand', 'asset', false, null, $cashDetailTypeId, $currentAssetsTypeId);
        $cash = $this->account($company, '1010', 'Main Cash', 'asset', true, $cashGroup, $cashDetailTypeId, $currentAssetsTypeId);
        $openingEquity = $this->account($company, '3000', 'Opening Equity', 'equity');
        $sales = $this->account($company, '4000', 'Sales Revenue', 'revenue');
        $rent = $this->account($company, '5000', 'Rent Expense', 'expense');
        $equipment = $this->account($company, '1500', 'Equipment', 'asset', true, null, $fixedAssetDetailTypeId, $fixedAssetsTypeId);
        $loan = $this->account($company, '2100', 'Bank Loan', 'liability', true, null, $loanDetailTypeId, $currentLiabilitiesTypeId);

        $this->entry($company, $fundSource, '2026-03-31', [
            [$cash, 'debit', 1000],
            [$openingEquity, 'credit', 1000],
        ]);

        $this->entry($company, $fundSource, '2026-04-10', [
            [$cash, 'debit', 300],
            [$sales, 'credit', 300],
        ]);
        $this->entry($company, $fundSource, '2026-04-15', [
            [$rent, 'debit', 100],
            [$cash, 'credit', 100],
        ]);

        $this->entry($company, $fundSource, '2026-05-05', [
            [$cash, 'debit', 500],
            [$sales, 'credit', 500],
        ]);
        $this->entry($company, $fundSource, '2026-05-08', [
            [$rent, 'debit', 150],
            [$cash, 'credit', 150],
        ]);
        $this->entry($company, $fundSource, '2026-05-12', [
            [$equipment, 'debit', 200],
            [$cash, 'credit', 200],
        ]);
        $this->entry($company, $fundSource, '2026-05-20', [
            [$cash, 'debit', 400],
            [$loan, 'credit', 400],
        ]);

        $this->entry($company, $fundSource, '2026-05-25', [
            [$cash, 'debit', 999],
            [$sales, 'credit', 999],
        ], 'draft');

        $otherCompany = Company::create([
            'name' => 'Other Cash Flow Co',
            'slug' => 'other-cash-flow-co',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);
        $otherFundSource = $this->fundSource($otherCompany);
        $otherCurrentAssetsTypeId = $this->accountType('other-current-assets', 'Other Current Assets', 'asset');
        $otherCashDetailTypeId = $this->detailType($otherCurrentAssetsTypeId, 'cash-in-hand', 'Cash In Hand');
        $otherCashGroup = $this->account($otherCompany, '9100', 'Cash In Hand', 'asset', false, null, $otherCashDetailTypeId, $otherCurrentAssetsTypeId);
        $otherCash = $this->account($otherCompany, '9110', 'Other Cash', 'asset', true, $otherCashGroup, $otherCashDetailTypeId, $otherCurrentAssetsTypeId);
        $otherSales = $this->account($otherCompany, '9400', 'Other Sales', 'revenue');
        $this->entry($otherCompany, $otherFundSource, '2026-05-05', [
            [$otherCash, 'debit', 777],
            [$otherSales, 'credit', 777],
        ]);

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/cash-flow?startDate=2026-05-01&endDate=2026-05-31&compareEnabled=true&comparisonMode=previous_period'
        );

        $response->assertOk()
            ->assertJsonPath('openingBalance', 1200)
            ->assertJsonPath('operatingTotal', 350)
            ->assertJsonPath('investingTotal', -200)
            ->assertJsonPath('financingTotal', 400)
            ->assertJsonPath('netChange', 550)
            ->assertJsonPath('endingBalance', 1750)
            ->assertJsonPath('comparisonPeriod.startDate', '2026-04-01')
            ->assertJsonPath('comparisonPeriod.endDate', '2026-04-30')
            ->assertJsonPath('comparison.openingBalance', 1000)
            ->assertJsonPath('comparison.operatingTotal', 200)
            ->assertJsonPath('comparison.netChange', 200)
            ->assertJsonPath('comparison.endingBalance', 1200)
            ->assertJsonPath('sections.0.key', 'operating')
            ->assertJsonPath('sections.0.currentTotal', 350)
            ->assertJsonPath('sections.0.previousTotal', 200)
            ->assertJsonPath('sections.0.varianceAmount', 150)
            ->assertJsonPath('summary.netCashFlowVariance', 350)
            ->assertJsonPath('summary.endingCashVariance', 550);
    }

    public function test_cash_flow_comparison_modes_resolve_previous_year_and_custom_ranges(): void
    {
        [$company, $headers] = $this->actingAsCompanyOwner();
        $currentAssetsTypeId = $this->accountType('current-assets-compare', 'Current Assets Compare', 'asset');
        $cashDetailTypeId = $this->detailType($currentAssetsTypeId, 'cash-in-hand', 'Cash In Hand');
        $cashGroup = $this->account($company, '1000', 'Cash In Hand', 'asset', false, null, $cashDetailTypeId, $currentAssetsTypeId);
        $this->account($company, '1010', 'Main Cash', 'asset', true, $cashGroup, $cashDetailTypeId, $currentAssetsTypeId);

        $this->withHeaders($headers)
            ->getJson('/api/v1/cash-flow?startDate=2026-05-01&endDate=2026-05-31&compareEnabled=true&comparisonMode=previous_year')
            ->assertOk()
            ->assertJsonPath('comparisonPeriod.startDate', '2025-05-01')
            ->assertJsonPath('comparisonPeriod.endDate', '2025-05-31');

        $this->withHeaders($headers)
            ->getJson('/api/v1/cash-flow?startDate=2026-05-01&endDate=2026-05-31&compareEnabled=true&comparisonMode=custom&comparisonStartDate=2026-01-01&comparisonEndDate=2026-01-31')
            ->assertOk()
            ->assertJsonPath('comparisonPeriod.startDate', '2026-01-01')
            ->assertJsonPath('comparisonPeriod.endDate', '2026-01-31');
    }

    public function test_cash_flow_detects_cash_accounts_by_detail_type_even_when_branch_names_are_customized(): void
    {
        [$company, $headers] = $this->actingAsCompanyOwner();
        $fundSource = $this->fundSource($company);
        $currentAssetsTypeId = $this->accountType('current-assets-custom-cash', 'Current Assets Custom Cash', 'asset');
        $cashDetailTypeId = $this->detailType($currentAssetsTypeId, 'cash-in-hand', 'Cash In Hand');

        $cashGroup = $this->account(
            $company,
            '1000',
            'Operating Funds',
            'asset',
            false,
            null,
            $cashDetailTypeId,
            $currentAssetsTypeId
        );
        $cash = $this->account(
            $company,
            '1010',
            'Petty Cash Drawer',
            'asset',
            true,
            $cashGroup,
            $cashDetailTypeId,
            $currentAssetsTypeId
        );
        $sales = $this->account($company, '4000', 'Sales Revenue', 'revenue');

        $this->entry($company, $fundSource, '2026-05-05', [
            [$cash, 'debit', 500],
            [$sales, 'credit', 500],
        ]);

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/cash-flow?startDate=2026-05-01&endDate=2026-05-31'
        );

        $response->assertOk()
            ->assertJsonPath('operatingTotal', 500)
            ->assertJsonPath('netChange', 500)
            ->assertJsonPath('endingBalance', 500);
    }

    public function test_cash_flow_classifies_working_capital_receipts_and_payments_as_operating(): void
    {
        [$company, $headers] = $this->actingAsCompanyOwner();
        $fundSource = $this->fundSource($company);
        $currentAssetsTypeId = $this->accountType('current-assets-working-capital', 'Current Assets Working Capital', 'asset');
        $currentLiabilitiesTypeId = $this->accountType('current-liabilities-working-capital', 'Current Liabilities Working Capital', 'liability');
        $cashDetailTypeId = $this->detailType($currentAssetsTypeId, 'cash-in-hand', 'Cash In Hand');
        $receivablesDetailTypeId = $this->detailType($currentAssetsTypeId, 'accounts-receivable', 'Accounts Receivable');
        $stockAssetsDetailTypeId = $this->detailType($currentAssetsTypeId, 'stock-assets', 'Stock Assets');
        $taxLiabilityDetailTypeId = $this->detailType($currentLiabilitiesTypeId, 'duties-and-taxes', 'Duties and Taxes');

        $cashGroup = $this->account($company, '1000', 'Cash In Hand', 'asset', false, null, $cashDetailTypeId, $currentAssetsTypeId);
        $cash = $this->account($company, '1010', 'Main Cash', 'asset', true, $cashGroup, $cashDetailTypeId, $currentAssetsTypeId);
        $receivable = $this->account($company, '1310', 'Sales Receivable', 'asset', true, null, $receivablesDetailTypeId, $currentAssetsTypeId);
        $stock = $this->account($company, '1410', 'Stock In Hand', 'asset', true, null, $stockAssetsDetailTypeId, $currentAssetsTypeId);
        $taxPayable = $this->account($company, '2311', 'Sales Tax Payable', 'liability', true, null, $taxLiabilityDetailTypeId, $currentLiabilitiesTypeId);

        $this->entry($company, $fundSource, '2026-05-01', [
            [$cash, 'debit', 400],
            [$receivable, 'credit', 400],
        ]);
        $this->entry($company, $fundSource, '2026-05-10', [
            [$stock, 'debit', 150],
            [$cash, 'credit', 150],
        ]);
        $this->entry($company, $fundSource, '2026-05-15', [
            [$taxPayable, 'debit', 50],
            [$cash, 'credit', 50],
        ]);

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/cash-flow?startDate=2026-05-01&endDate=2026-05-31'
        );

        $response->assertOk()
            ->assertJsonPath('operatingTotal', 200)
            ->assertJsonPath('investingTotal', 0)
            ->assertJsonPath('financingTotal', 0)
            ->assertJsonPath('netChange', 200);
    }

    public function test_cash_flow_direct_method_classifies_cash_movements_and_reconciles_review_items(): void
    {
        [$company, $headers] = $this->actingAsCompanyOwner();
        $fundSource = $this->fundSource($company);

        $currentAssetsTypeId = $this->accountType('current-assets-direct-cash-flow', 'Current Assets Direct Cash Flow', 'asset');
        $fixedAssetsTypeId = $this->accountType('fixed-assets-direct-cash-flow', 'Fixed Assets Direct Cash Flow', 'asset');
        $currentLiabilitiesTypeId = $this->accountType('current-liabilities-direct-cash-flow', 'Current Liabilities Direct Cash Flow', 'liability');
        $nonCurrentLiabilitiesTypeId = $this->accountType('non-current-liabilities-direct-cash-flow', 'Non-current Liabilities Direct Cash Flow', 'liability');
        $otherAssetsTypeId = $this->accountType('other-assets-cash-flow-review', 'Other Assets Cash Flow Review', 'asset');

        $cashDetailTypeId = $this->detailType($currentAssetsTypeId, 'cash-in-hand', 'Cash In Hand');
        $receivablesDetailTypeId = $this->detailType($currentAssetsTypeId, 'accounts-receivable', 'Accounts Receivable');
        $taxAssetDetailTypeId = $this->detailType($currentAssetsTypeId, 'tax-assets', 'Tax Assets');
        $equipmentDetailTypeId = $this->detailType($fixedAssetsTypeId, 'office-equipment', 'Office Equipment');
        $payablesDetailTypeId = $this->detailType($currentLiabilitiesTypeId, 'accounts-payable', 'Accounts Payable');
        $loanDetailTypeId = $this->detailType($nonCurrentLiabilitiesTypeId, 'loans-liabilities', 'Loans Liabilities');
        $unmappedDetailTypeId = $this->detailType($otherAssetsTypeId, 'other-asset-review', 'Other Asset Review');

        $cashGroup = $this->account($company, '1000', 'Cash In Hand', 'asset', false, null, $cashDetailTypeId, $currentAssetsTypeId);
        $cash = $this->account($company, '1010', 'Main Cash', 'asset', true, $cashGroup, $cashDetailTypeId, $currentAssetsTypeId);
        $accountsReceivable = $this->account($company, '1200', 'Accounts Receivable', 'asset', true, null, $receivablesDetailTypeId, $currentAssetsTypeId);
        $withholdingTaxReceivable = $this->account($company, '1210', 'Withholding Tax Receivable', 'asset', true, null, $taxAssetDetailTypeId, $currentAssetsTypeId);
        $equipment = $this->account($company, '1500', 'Equipment', 'asset', true, null, $equipmentDetailTypeId, $fixedAssetsTypeId);
        $accountsPayable = $this->account($company, '2100', 'Accounts Payable', 'liability', true, null, $payablesDetailTypeId, $currentLiabilitiesTypeId);
        $loanPayable = $this->account($company, '2500', 'Loan Payable', 'liability', true, null, $loanDetailTypeId, $nonCurrentLiabilitiesTypeId);
        $ownerCapital = $this->account($company, '3000', 'Owner Capital', 'equity');
        $ownerDrawing = $this->account($company, '3100', 'Owner Drawing', 'equity');
        $serviceRevenue = $this->account($company, '4000', 'Service Revenue', 'revenue');
        $rentExpense = $this->account($company, '5000', 'Rent Expense', 'expense');
        $depreciationExpense = $this->account($company, '5100', 'Depreciation Expense', 'expense');
        $accumulatedDepreciation = $this->account($company, '1590', 'Accumulated Depreciation', 'asset', true, null, $equipmentDetailTypeId, $fixedAssetsTypeId);
        $mysteryClearing = $this->account($company, '1999', 'Mystery Clearing', 'asset', true, null, $unmappedDetailTypeId, $otherAssetsTypeId);

        $this->entry($company, $fundSource, '2026-04-30', [
            [$cash, 'debit', 1000],
            [$ownerCapital, 'credit', 1000],
        ]);

        $this->entry($company, $fundSource, '2026-05-01', [
            [$cash, 'debit', 500],
            [$serviceRevenue, 'credit', 500],
        ]);
        $this->entry($company, $fundSource, '2026-05-02', [
            [$cash, 'debit', 300],
            [$accountsReceivable, 'credit', 300],
        ]);
        $this->entry($company, $fundSource, '2026-05-03', [
            [$rentExpense, 'debit', 150],
            [$cash, 'credit', 150],
        ]);
        $this->entry($company, $fundSource, '2026-05-04', [
            [$accountsPayable, 'debit', 200],
            [$cash, 'credit', 200],
        ]);
        $this->entry($company, $fundSource, '2026-05-05', [
            [$equipment, 'debit', 700],
            [$cash, 'credit', 700],
        ]);
        $this->entry($company, $fundSource, '2026-05-06', [
            [$cash, 'debit', 1000],
            [$loanPayable, 'credit', 1000],
        ]);
        $this->entry($company, $fundSource, '2026-05-07', [
            [$loanPayable, 'debit', 400],
            [$cash, 'credit', 400],
        ]);
        $this->entry($company, $fundSource, '2026-05-08', [
            [$cash, 'debit', 800],
            [$ownerCapital, 'credit', 800],
        ]);
        $this->entry($company, $fundSource, '2026-05-09', [
            [$ownerDrawing, 'debit', 250],
            [$cash, 'credit', 250],
        ]);
        $this->entry($company, $fundSource, '2026-05-10', [
            [$depreciationExpense, 'debit', 100],
            [$accumulatedDepreciation, 'credit', 100],
        ]);
        $this->entry($company, $fundSource, '2026-05-11', [
            [$cash, 'debit', 100],
            [$withholdingTaxReceivable, 'debit', 10],
            [$serviceRevenue, 'credit', 110],
        ]);
        $this->entry($company, $fundSource, '2026-05-12', [
            [$cash, 'debit', 50],
            [$mysteryClearing, 'credit', 50],
        ]);
        $this->entry($company, $fundSource, '2026-05-13', [
            [$cash, 'debit', 999],
            [$serviceRevenue, 'credit', 999],
        ], 'draft');

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/cash-flow?startDate=2026-05-01&endDate=2026-05-31'
        );

        $response->assertOk()
            ->assertJsonPath('openingBalance', 1000)
            ->assertJsonPath('operatingTotal', 550)
            ->assertJsonPath('investingTotal', -700)
            ->assertJsonPath('financingTotal', 1150)
            ->assertJsonPath('netChange', 1000)
            ->assertJsonPath('endingBalance', 2050)
            ->assertJsonPath('reconciliationDifference', 50)
            ->assertJsonCount(1, 'unmappedTransactions');

        $payload = $response->json();
        $items = collect($payload['items']);

        $this->assertSame(600.0, (float) $items->firstWhere('accountName', 'Service Revenue')['total']);
        $this->assertSame(300.0, (float) $items->firstWhere('accountName', 'Accounts Receivable')['total']);
        $this->assertSame(-150.0, (float) $items->firstWhere('accountName', 'Rent Expense')['total']);
        $this->assertSame(-200.0, (float) $items->firstWhere('accountName', 'Accounts Payable')['total']);
        $this->assertSame(-700.0, (float) $items->firstWhere('accountName', 'Equipment')['total']);
        $this->assertSame(600.0, (float) $items->firstWhere('accountName', 'Loan Payable')['total']);
        $this->assertSame(800.0, (float) $items->firstWhere('accountName', 'Owner Capital')['total']);
        $this->assertSame(-250.0, (float) $items->firstWhere('accountName', 'Owner Drawing')['total']);
        $this->assertNull($items->firstWhere('accountName', 'Depreciation Expense'));

        $this->assertSame('Mystery Clearing', $payload['unmappedAccounts'][0]['accountName']);
        $this->assertSame(
            2050.0,
            (float) $payload['openingBalance'] + (float) $payload['netChange'] + (float) $payload['reconciliationDifference']
        );
        $this->assertNotEmpty($payload['warnings']);
    }

    /**
     * @return array{0: Company, 1: array<string, string>}
     */
    private function actingAsCompanyOwner(): array
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $company = Company::create([
            'name' => 'Cash Flow Co '.$user->id,
            'slug' => 'cash-flow-co-'.$user->id,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->getKey(),
        ]);

        $user->forceFill(['company_id' => $company->getKey()])->save();

        return [
            $company,
            ['X-Company-ID' => (string) $company->getKey()],
        ];
    }

    private function fundSource(Company $company): FundSource
    {
        return FundSource::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'General Fund',
            'status' => 'active',
        ]);
    }

    private function account(
        Company $company,
        string $code,
        string $name,
        string $type,
        bool $isPosting = true,
        ?Account $parent = null,
        ?int $detailTypeId = null,
        ?int $accountTypeId = null
    ): Account {
        return Account::factory()->create([
            'company_id' => $company->getKey(),
            'tenant_id' => $company->getKey(),
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
            'parent_id' => $parent?->getKey(),
            'is_posting' => $isPosting,
            'status' => 'active',
        ]);
    }

    private function accountType(string $code, string $name, string $coreType): int
    {
        return DB::table('account_types')->insertGetId([
            'code' => $code,
            'name' => $name,
            'core_type' => $coreType,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function detailType(int $accountTypeId, string $code, string $name): int
    {
        return DB::table('detail_types')->insertGetId([
            'account_type_id' => $accountTypeId,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param array<int, array{0: Account, 1: string, 2: int|float}> $items
     */
    private function entry(
        Company $company,
        FundSource $fundSource,
        string $entryDate,
        array $items,
        string $status = 'posted'
    ): JournalEntry {
        $entry = JournalEntry::query()->create([
            'company_id' => $company->getKey(),
            'prefix' => 'JE',
            'entry_number' => JournalEntry::query()->count() + 1,
            'entry_date' => $entryDate,
            'type' => 'general',
            'fund_source_id' => $fundSource->getKey(),
            'reference_number' => null,
            'notes' => null,
            'amount' => collect($items)->where(1, 'debit')->sum(2),
            'status' => $status,
        ]);

        foreach ($items as [$account, $type, $amount]) {
            JournalItem::query()->create([
                'journal_entry_id' => $entry->getKey(),
                'account_id' => $account->getKey(),
                'description' => null,
                'type' => $type,
                'amount' => $amount,
            ]);
        }

        return $entry;
    }
}

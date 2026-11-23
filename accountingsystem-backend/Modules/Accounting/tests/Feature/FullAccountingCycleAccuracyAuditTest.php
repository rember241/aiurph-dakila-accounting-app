<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Models\JournalEntries\JournalNumber;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Models\Taxes\Tax;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoicePayment;
use Modules\Users\Models\Company;
use Tests\TestCase;

final class FullAccountingCycleAccuracyAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * This is an independent accounting-cycle audit with realistic fixed data.
     * It intentionally does not reuse the existing module test helpers.
     */
    public function test_full_erp_accounting_cycle_inventory_sales_payments_billing_and_reports_are_accurate(): void
    {
        [$company, $headers] = $this->authenticateOwnerCompany();
        $typeIds = $this->createAccountTypes();
        $fundSource = $this->createFundSource($company);
        $this->createOpenAccountingPeriod($company, '2026-01-01', '2026-12-31');

        $accounts = $this->createChartOfAccounts($company, $typeIds);
        $tax = Tax::query()->create([
            'company_id' => (string) $company->getKey(),
            'name' => 'VAT 12%',
            'rate' => 12,
            'status' => 'active',
        ]);

        $company->forceFill([
            'default_accounts_receivable_account_id' => $accounts['ar']->getKey(),
            'default_sales_income_account_id' => $accounts['sales']->getKey(),
            'default_inventory_asset_account_id' => $accounts['inventory']->getKey(),
            'default_cogs_account_id' => $accounts['cogs']->getKey(),
            'default_sales_tax_payable_account_id' => $accounts['vat']->getKey(),
            'default_cash_receipt_account_id' => $accounts['cash']->getKey(),
            'default_bank_receipt_account_id' => $accounts['bank']->getKey(),
            'default_online_receipt_account_id' => $accounts['online']->getKey(),
            'default_check_receipt_account_id' => $accounts['check']->getKey(),
        ])->save();

        $this->postOpeningBalance($company, $fundSource, $accounts);

        $crownCustomerId = $this->createCustomer($headers, 'CROWN-001', 'Crown Foods Corporation');
        $northstarCustomerId = $this->createCustomer($headers, 'NORTH-001', 'Northstar Realty Services');
        $riveraCustomerId = $this->createCustomer($headers, 'RIVERA-001', 'Rivera Trading Co.');

        $cctvItemId = $this->createInventoryItem($headers, [
            'code' => 'CCTV-CAM',
            'name' => 'CCTV Camera',
            'unit_of_measure' => 'PCS',
            'quantity_on_hand' => 20,
            'sales_price' => 4500,
            'cost_price' => 2500,
            'item_type' => 'inventory',
            'reorder_point' => 5,
        ]);
        $cableItemId = $this->createInventoryItem($headers, [
            'code' => 'NET-CABLE',
            'name' => 'Network Cable Roll',
            'unit_of_measure' => 'ROLL',
            'quantity_on_hand' => 100,
            'sales_price' => 650,
            'cost_price' => 300,
            'item_type' => 'inventory',
            'reorder_point' => 20,
        ]);
        $installServiceId = $this->createInventoryItem($headers, [
            'code' => 'INSTALL-SVC',
            'name' => 'Installation Service',
            'unit_of_measure' => 'JOB',
            'quantity_on_hand' => 0,
            'sales_price' => 2000,
            'cost_price' => null,
            'item_type' => 'service',
            'reorder_point' => null,
        ]);
        $monitoringServiceId = $this->createInventoryItem($headers, [
            'code' => 'MONITOR-SVC',
            'name' => 'Monthly Monitoring Service',
            'unit_of_measure' => 'MONTH',
            'quantity_on_hand' => 0,
            'sales_price' => 3500,
            'cost_price' => null,
            'item_type' => 'service',
            'reorder_point' => null,
        ]);

        $draftInvoiceId = $this->createInvoice($headers, $riveraCustomerId, '2026-05-03', 'draft', [
            [$installServiceId, $tax->getKey(), 1],
        ]);

        $crownInvoiceId = $this->createInvoice($headers, $crownCustomerId, '2026-05-05', 'post', [
            [$cctvItemId, $tax->getKey(), 2],
            [$cableItemId, $tax->getKey(), 10],
            [$installServiceId, $tax->getKey(), 1],
        ]);

        $northstarInvoiceId = $this->createInvoice($headers, $northstarCustomerId, '2026-05-08', 'post', [
            [$monitoringServiceId, $tax->getKey(), 1],
        ]);

        $riveraInvoiceId = $this->createInvoice($headers, $riveraCustomerId, '2026-05-12', 'post', [
            [$cctvItemId, $tax->getKey(), 1],
        ]);

        $this->recordPayment($headers, $crownInvoiceId, '2026-05-15', 5000, 'bank_transfer', $accounts['bank'], 'CROWN-PMT-001');
        $this->recordPayment($headers, $northstarInvoiceId, '2026-05-16', 3920, 'online', $accounts['online'], 'NORTH-PMT-001');

        $this->withHeaders($headers)
            ->postJson("/api/v1/sales-invoices/{$riveraInvoiceId}/payments", [
                'payment_date' => '2026-05-20',
                'amount' => 999999,
                'payment_method' => 'cash',
                'receipt_account_id' => (string) $accounts['cash']->getKey(),
                'reference_no' => 'OVERPAY-001',
            ])
            ->assertStatus(422);

        $this->postRentExpense($company, $fundSource, $accounts);

        $failures = [];

        $this->checkInventoryState($failures, $cctvItemId, $cableItemId);
        $this->checkDraftDidNotPost($failures, $draftInvoiceId);
        $this->checkPaymentState($failures, $crownInvoiceId, $northstarInvoiceId);
        $this->checkLedgerBalances($failures, $company, $accounts);
        $this->checkTrialBalanceReport($failures, $headers);
        $this->checkIncomeStatementReport($failures, $headers, [$accounts['sales'], $accounts['cogs'], $accounts['rent']]);
        $this->checkBalanceSheetReport($failures, $headers, [
            $accounts['bank'],
            $accounts['cash'],
            $accounts['online'],
            $accounts['ar'],
            $accounts['inventory'],
            $accounts['equipment'],
            $accounts['ap'],
            $accounts['vat'],
            $accounts['equity'],
        ]);
        $this->checkCashFlowReport($failures, $headers);
        $this->checkBillingStatements($failures, $headers, $crownCustomerId, $northstarCustomerId, $riveraCustomerId);

        if ($failures !== []) {
            $this->fail("Full accounting cycle audit found mismatches:\n- ".implode("\n- ", $failures));
        }

        $this->assertTrue(true);
    }

    /**
     * @return array{0: Company, 1: array<string, string>}
     */
    private function authenticateOwnerCompany(): array
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [], 'sanctum');

        $company = Company::query()->create([
            'name' => 'Bacmor Security Agency QA Corp',
            'display_name' => 'Bacmor Security Agency QA Corp',
            'slug' => 'bacmor-security-agency-qa',
            'status' => 'active',
            'timezone' => 'Asia/Manila',
            'owner_user_id' => (string) $user->getKey(),
        ]);

        $user->forceFill(['company_id' => (string) $company->getKey()])->save();

        return [$company, ['X-Company-ID' => (string) $company->getKey()]];
    }

    /**
     * @return array<string, int>
     */
    private function createAccountTypes(): array
    {
        $types = [
            'current_assets' => ['current-assets-audit', 'Current Assets', 'asset'],
            'fixed_assets' => ['fixed-assets-audit', 'Fixed Assets', 'asset'],
            'current_liabilities' => ['current-liabilities-audit', 'Current Liabilities', 'liability'],
            'equity' => ['equity-audit', 'Equity', 'equity'],
            'revenue' => ['revenue-audit', 'Revenue', 'revenue'],
            'expense' => ['expense-audit', 'Expenses', 'expense'],
        ];

        $ids = [];
        foreach ($types as $key => [$code, $name, $coreType]) {
            $ids[$key] = DB::table('account_types')->insertGetId([
                'code' => $code,
                'name' => $name,
                'core_type' => $coreType,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $detailRows = [
            'cash' => [$ids['current_assets'], 'cash-in-hand', 'Cash In Hand'],
            'bank' => [$ids['current_assets'], 'bank-accounts', 'Bank Accounts'],
            'receivable' => [$ids['current_assets'], 'accounts-receivable', 'Accounts Receivable'],
            'stock' => [$ids['current_assets'], 'stock-assets', 'Stock Assets'],
            'fixed_asset' => [$ids['fixed_assets'], 'office-equipment', 'Office Equipment'],
            'payable' => [$ids['current_liabilities'], 'accounts-payable', 'Accounts Payable'],
            'tax' => [$ids['current_liabilities'], 'duties-and-taxes', 'Duties and Taxes'],
        ];

        foreach ($detailRows as $key => [$accountTypeId, $code, $name]) {
            $ids[$key.'_detail'] = DB::table('detail_types')->insertGetId([
                'account_type_id' => $accountTypeId,
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function createFundSource(Company $company): FundSource
    {
        return FundSource::query()->create([
            'company_id' => (string) $company->getKey(),
            'name' => 'General Fund',
            'status' => 'active',
        ]);
    }

    private function createOpenAccountingPeriod(Company $company, string $startDate, string $endDate): void
    {
        $fiscalYear = FiscalYear::query()->create([
            'tenant_id' => (string) $company->getKey(),
            'company_id' => (string) $company->getKey(),
            'code' => 'FY2026-AUDIT',
            'name' => 'FY 2026 Audit',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'open',
            'is_current' => true,
        ]);

        AccountingPeriod::query()->create([
            'tenant_id' => (string) $company->getKey(),
            'company_id' => (string) $company->getKey(),
            'fiscal_year_id' => (string) $fiscalYear->getKey(),
            'period_number' => 5,
            'name' => 'May 2026',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);
    }

    /**
     * @param array<string, int> $typeIds
     * @return array<string, Account>
     */
    private function createChartOfAccounts(Company $company, array $typeIds): array
    {
        $currentAssetsHeader = $this->account($company, '1000', 'Current Assets', 'asset', false, null, $typeIds['current_assets'], null);
        $currentLiabilitiesHeader = $this->account($company, '2000', 'Current Liabilities', 'liability', false, null, $typeIds['current_liabilities'], $typeIds['payable_detail']);

        return [
            'currentAssetsHeader' => $currentAssetsHeader,
            'currentLiabilitiesHeader' => $currentLiabilitiesHeader,
            'bank' => $this->account($company, '1010', 'Bank Account', 'asset', true, $currentAssetsHeader, $typeIds['current_assets'], $typeIds['bank_detail']),
            'cash' => $this->account($company, '1020', 'Cash', 'asset', true, $currentAssetsHeader, $typeIds['current_assets'], $typeIds['cash_detail']),
            'online' => $this->account($company, '1030', 'Online Wallet', 'asset', true, $currentAssetsHeader, $typeIds['current_assets'], $typeIds['bank_detail']),
            'check' => $this->account($company, '1040', 'Checks Clearing', 'asset', true, $currentAssetsHeader, $typeIds['current_assets'], $typeIds['bank_detail']),
            'ar' => $this->account($company, '1100', 'Accounts Receivable', 'asset', true, $currentAssetsHeader, $typeIds['current_assets'], $typeIds['receivable_detail']),
            'inventory' => $this->account($company, '1200', 'Inventory Asset', 'asset', true, $currentAssetsHeader, $typeIds['current_assets'], $typeIds['stock_detail']),
            'equipment' => $this->account($company, '1500', 'Equipment', 'asset', true, null, $typeIds['fixed_assets'], $typeIds['fixed_asset_detail']),
            'ap' => $this->account($company, '2100', 'Accounts Payable', 'liability', true, $currentLiabilitiesHeader, $typeIds['current_liabilities'], $typeIds['payable_detail']),
            'vat' => $this->account($company, '2200', 'VAT Payable', 'liability', true, $currentLiabilitiesHeader, $typeIds['current_liabilities'], $typeIds['tax_detail']),
            'equity' => $this->account($company, '3000', 'Opening Balance Equity', 'equity', true, null, $typeIds['equity'], null),
            'sales' => $this->account($company, '4000', 'Sales Revenue', 'revenue', true, null, $typeIds['revenue'], null),
            'cogs' => $this->account($company, '5000', 'Cost of Goods Sold', 'expense', true, null, $typeIds['expense'], null),
            'rent' => $this->account($company, '5100', 'Rent Expense', 'expense', true, null, $typeIds['expense'], null),
        ];
    }

    private function account(
        Company $company,
        string $code,
        string $name,
        string $type,
        bool $isPosting,
        ?Account $parent,
        ?int $accountTypeId,
        ?int $detailTypeId
    ): Account {
        return Account::query()->create([
            'tenant_id' => (string) $company->getKey(),
            'company_id' => (string) $company->getKey(),
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

    /**
     * @param array<string, Account> $accounts
     */
    private function postOpeningBalance(Company $company, FundSource $fundSource, array $accounts): void
    {
        $this->entry($company, $fundSource, '2026-04-30', 'OPEN', [
            [$accounts['bank'], 'debit', 150000],
            [$accounts['cash'], 'debit', 20000],
            [$accounts['inventory'], 'debit', 80000],
            [$accounts['ar'], 'debit', 30000],
            [$accounts['equipment'], 'debit', 100000],
            [$accounts['ap'], 'credit', 40000],
            [$accounts['equity'], 'credit', 340000],
        ]);
    }

    /**
     * @param array<string, Account> $accounts
     */
    private function postRentExpense(Company $company, FundSource $fundSource, array $accounts): void
    {
        $this->entry($company, $fundSource, '2026-05-18', 'RENT', [
            [$accounts['rent'], 'debit', 12000],
            [$accounts['bank'], 'credit', 12000],
        ]);
    }

    /**
     * @param array<int, array{0: Account, 1: string, 2: int|float}> $items
     */
    private function entry(Company $company, FundSource $fundSource, string $date, string $prefix, array $items): JournalEntry
    {
        $entry = JournalEntry::query()->create([
            'company_id' => (string) $company->getKey(),
            'prefix' => $prefix,
            'entry_number' => JournalEntry::query()->where('company_id', $company->getKey())->count() + 1,
            'entry_date' => $date,
            'type' => 'general',
            'fund_source_id' => (string) $fundSource->getKey(),
            'reference_number' => null,
            'notes' => null,
            'amount' => collect($items)->where(1, 'debit')->sum(2),
            'status' => 'posted',
        ]);

        foreach ($items as [$account, $type, $amount]) {
            JournalItem::query()->create([
                'journal_entry_id' => (string) $entry->getKey(),
                'account_id' => (string) $account->getKey(),
                'description' => null,
                'type' => $type,
                'amount' => $amount,
            ]);
        }

        return $entry;
    }

    private function createCustomer(array $headers, string $code, string $name): string
    {
        return (string) $this->withHeaders($headers)->postJson('/api/v1/customers', [
            'code' => $code,
            'name' => $name,
            'customer_group' => 'commercial',
            'email' => strtolower($code).'@example.test',
            'phone' => '09171234567',
            'address' => 'Baguio City',
            'status' => 'active',
        ])->assertCreated()->json('data.id');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createInventoryItem(array $headers, array $payload): string
    {
        return (string) $this->withHeaders($headers)
            ->postJson('/api/v1/inventories', $payload + [
                'description' => null,
                'pricing_as_of' => '2026-05-01',
                'stock_as_of' => '2026-05-01',
                'category' => 'Security Systems',
                'status' => 'active',
            ])
            ->assertCreated()
            ->json('data.id');
    }

    /**
     * @param array<int, array{0:string, 1:string, 2:int|float}> $lines
     */
    private function createInvoice(array $headers, string $customerId, string $invoiceDate, string $submitAction, array $lines): string
    {
        return (string) $this->withHeaders($headers)
            ->postJson('/api/v1/sales-invoices', [
                'submit_action' => $submitAction,
                'invoice_date' => $invoiceDate,
                'due_date' => '2026-05-31',
                'customer_id' => $customerId,
                'notes' => "QA {$submitAction} invoice",
                'lines' => array_map(static fn (array $line): array => [
                    'inventory_item_id' => $line[0],
                    'tax_id' => $line[1],
                    'quantity' => $line[2],
                ], $lines),
            ])
            ->assertCreated()
            ->json('data.id');
    }

    private function recordPayment(
        array $headers,
        string $invoiceId,
        string $date,
        int|float $amount,
        string $method,
        Account $receiptAccount,
        string $reference
    ): void {
        $this->withHeaders($headers)->postJson("/api/v1/sales-invoices/{$invoiceId}/payments", [
            'payment_date' => $date,
            'amount' => $amount,
            'payment_method' => $method,
            'receipt_account_id' => (string) $receiptAccount->getKey(),
            'reference_no' => $reference,
        ])->assertCreated();
    }

    private function checkInventoryState(array &$failures, string $cctvItemId, string $cableItemId): void
    {
        $cctv = InventoryItem::query()->findOrFail($cctvItemId);
        $cable = InventoryItem::query()->findOrFail($cableItemId);

        $this->expectAmount($failures, 'Inventory module: CCTV quantity after posted sales', 17, (float) $cctv->quantity_on_hand);
        $this->expectAmount($failures, 'Inventory module: Network Cable quantity after posted sales', 90, (float) $cable->quantity_on_hand);

        $this->expectAmount($failures, 'Inventory module: CCTV outbound movement rows', 2, InventoryMovement::query()->where('inventory_item_id', $cctvItemId)->where('direction', 'out')->count());
        $this->expectAmount($failures, 'Inventory module: Network Cable outbound movement rows', 1, InventoryMovement::query()->where('inventory_item_id', $cableItemId)->where('direction', 'out')->count());
    }

    private function checkDraftDidNotPost(array &$failures, string $draftInvoiceId): void
    {
        $draft = SalesInvoice::query()->findOrFail($draftInvoiceId);

        if ((string) $draft->status !== 'draft') {
            $failures[] = sprintf('Sales module: draft invoice status expected draft, got %s', (string) $draft->status);
        }

        if ($draft->journal_entry_id !== null || $draft->accounting_posted_at !== null) {
            $failures[] = 'Sales module: draft invoice has accounting linkage even though it should not be posted';
        }

        $this->expectAmount(
            $failures,
            'Sales module: draft invoice must not create inventory movement',
            0,
            InventoryMovement::query()->where('sales_invoice_id', $draftInvoiceId)->count()
        );
    }

    private function checkPaymentState(array &$failures, string $crownInvoiceId, string $northstarInvoiceId): void
    {
        $this->expectAmount($failures, 'Payments module: Crown payment total', 5000, (float) SalesInvoicePayment::query()->where('sales_invoice_id', $crownInvoiceId)->whereNull('voided_at')->sum('amount'));
        $this->expectAmount($failures, 'Payments module: Northstar payment total', 3920, (float) SalesInvoicePayment::query()->where('sales_invoice_id', $northstarInvoiceId)->whereNull('voided_at')->sum('amount'));
    }

    /**
     * @param array<string, Account> $accounts
     */
    private function checkLedgerBalances(array &$failures, Company $company, array $accounts): void
    {
        $expected = [
            'bank' => 143000,
            'cash' => 20000,
            'online' => 3920,
            'ar' => 49640,
            'inventory' => 69500,
            'equipment' => 100000,
            'ap' => 40000,
            'vat' => 3060,
            'equity' => 340000,
            'sales' => 25500,
            'cogs' => 10500,
            'rent' => 12000,
        ];

        foreach ($expected as $key => $amount) {
            $this->expectAmount(
                $failures,
                "General ledger: {$accounts[$key]->name} ending balance",
                $amount,
                $this->accountBalance($company, $accounts[$key])
            );
        }

        $debits = (float) JournalItem::query()
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $company->getKey())
            ->where('journal_entries.status', 'posted')
            ->where('journal_items.type', 'debit')
            ->sum('journal_items.amount');

        $credits = (float) JournalItem::query()
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $company->getKey())
            ->where('journal_entries.status', 'posted')
            ->where('journal_items.type', 'credit')
            ->sum('journal_items.amount');

        $this->expectAmount($failures, 'General ledger: posted debits equal credits', $debits, $credits);
    }

    private function checkTrialBalanceReport(array &$failures, array $headers): void
    {
        $response = $this->withHeaders($headers)->getJson('/api/v1/trial-balance?date=2026-05-31')->assertOk();
        $rows = collect($response->json());

        $this->expectAmount($failures, 'Trial Balance report: total debit', 408560, (float) $rows->sum('debit'));
        $this->expectAmount($failures, 'Trial Balance report: total credit', 408560, (float) $rows->sum('credit'));
        $this->expectAmount($failures, 'Trial Balance report: Accounts Receivable', 49640, (float) ($rows->firstWhere('name', 'Accounts Receivable')['debit'] ?? 0));
    }

    /**
     * @param array<int, Account> $accounts
     */
    private function checkIncomeStatementReport(array &$failures, array $headers, array $accounts): void
    {
        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/income-statement?'.http_build_query([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
                'accountIds' => array_map(static fn (Account $account): string => (string) $account->getKey(), $accounts),
            ]))
            ->assertOk();

        $this->expectAmount($failures, 'Income Statement report: total revenue', 25500, (float) $response->json('totalRevenue.0'));
        $this->expectAmount($failures, 'Income Statement report: total expenses including COGS', 22500, (float) $response->json('totalExpense.0'));
        $this->expectAmount($failures, 'Income Statement report: net income', 3000, (float) $response->json('netTotal.0'));
    }

    /**
     * @param array<int, Account> $accounts
     */
    private function checkBalanceSheetReport(array &$failures, array $headers, array $accounts): void
    {
        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/balance-sheet?'.http_build_query([
                'date' => '2026-05-31',
                'accountIds' => array_map(static fn (Account $account): string => (string) $account->getKey(), $accounts),
            ]))
            ->assertOk();

        $this->expectAmount($failures, 'Balance Sheet report: total assets', 386060, (float) $response->json('totalAssets.0'));
        $this->expectAmount($failures, 'Balance Sheet report: total liabilities', 43060, (float) $response->json('totalLiabilities.0'));
        $this->expectAmount($failures, 'Balance Sheet report: total equity including current earnings', 343000, (float) $response->json('totalEquities.0'));
        $this->expectAmount($failures, 'Balance Sheet report: current period earnings equity row', 3000, (float) (collect($response->json('accounts'))->firstWhere('id', '__current_period_earnings')['balance'] ?? 0));
        $this->expectAmount($failures, 'Balance Sheet report: liabilities plus equity balances with assets', 386060, (float) $response->json('totalLiabilitiesEquities.0'));
    }

    private function checkCashFlowReport(array &$failures, array $headers): void
    {
        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/cash-flow?startDate=2026-05-01&endDate=2026-05-31')
            ->assertOk();

        $this->expectAmount($failures, 'Cash Flow report: opening cash and bank balance', 170000, (float) $response->json('openingBalance'));
        $this->expectAmount($failures, 'Cash Flow report: net cash change', -3080, (float) $response->json('netChange'));
        $this->expectAmount($failures, 'Cash Flow report: ending cash and bank balance', 166920, (float) $response->json('endingBalance'));
    }

    private function checkBillingStatements(
        array &$failures,
        array $headers,
        string $crownCustomerId,
        string $northstarCustomerId,
        string $riveraCustomerId
    ): void {
        $crown = $this->billingStatement($headers, $crownCustomerId);
        $northstar = $this->billingStatement($headers, $northstarCustomerId);
        $rivera = $this->billingStatement($headers, $riveraCustomerId);

        $this->expectAmount($failures, 'Billing Statement: Crown total invoiced', 19600, (float) $crown['summary']['total_invoiced']);
        $this->expectAmount($failures, 'Billing Statement: Crown total paid', 5000, (float) $crown['summary']['total_paid']);
        $this->expectAmount($failures, 'Billing Statement: Crown closing balance', 14600, (float) $crown['summary']['closing_balance']);

        $this->expectAmount($failures, 'Billing Statement: Northstar total invoiced', 3920, (float) $northstar['summary']['total_invoiced']);
        $this->expectAmount($failures, 'Billing Statement: Northstar total paid', 3920, (float) $northstar['summary']['total_paid']);
        $this->expectAmount($failures, 'Billing Statement: Northstar paid invoice remains visible in period history', 2, count($northstar['transactions']));

        $this->expectAmount($failures, 'Billing Statement: Rivera total invoiced excludes draft invoice', 5040, (float) $rivera['summary']['total_invoiced']);
        $this->expectAmount($failures, 'Billing Statement: Rivera closing balance excludes draft invoice', 5040, (float) $rivera['summary']['closing_balance']);
    }

    /**
     * @return array<string, mixed>
     */
    private function billingStatement(array $headers, string $customerId): array
    {
        return $this->withHeaders($headers)
            ->getJson("/api/v1/customers/{$customerId}/billing-statement?date_from=2026-05-01&date_to=2026-05-31")
            ->assertOk()
            ->json('data');
    }

    private function accountBalance(Company $company, Account $account): float
    {
        $totals = JournalItem::query()
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', (string) $company->getKey())
            ->where('journal_entries.status', 'posted')
            ->where('journal_items.account_id', (string) $account->getKey())
            ->selectRaw("SUM(CASE WHEN journal_items.type = 'debit' THEN journal_items.amount ELSE 0 END) as debits")
            ->selectRaw("SUM(CASE WHEN journal_items.type = 'credit' THEN journal_items.amount ELSE 0 END) as credits")
            ->first();

        $debits = (float) ($totals->debits ?? 0);
        $credits = (float) ($totals->credits ?? 0);

        return $account->normal_balance === 'debit'
            ? round($debits - $credits, 2)
            : round($credits - $debits, 2);
    }

    private function expectAmount(array &$failures, string $label, int|float $expected, int|float $actual): void
    {
        if (abs(round((float) $expected, 2) - round((float) $actual, 2)) > 0.01) {
            $failures[] = sprintf('%s expected %.2f, got %.2f', $label, $expected, $actual);
        }
    }
}

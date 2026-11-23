<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Users\Models\Company;

return new class extends Migration {
    private const DEFAULT_ACCOUNT_CODES = [
        'default_accounts_receivable_account_id' => '1310',
        'default_sales_income_account_id' => '4110',
        'default_inventory_asset_account_id' => '1410',
        'default_cogs_account_id' => '5111',
        'default_sales_tax_payable_account_id' => '2311',
    ];

    /**
     * @var list<array<string, mixed>>
     */
    private const TAX_ACCOUNTS = [
        [
            'code' => '1510',
            'name' => 'Input VAT',
            'type' => 'asset',
            'parent_code' => '1500',
            'account_type' => 'Current Assets',
            'detail_type' => 'Tax Assets',
        ],
        [
            'code' => '1520',
            'name' => 'Withholding Tax Receivable',
            'type' => 'asset',
            'parent_code' => '1500',
            'account_type' => 'Current Assets',
            'detail_type' => 'Tax Assets',
        ],
        [
            'code' => '2311',
            'name' => 'Sales Tax Payable',
            'type' => 'liability',
            'parent_code' => '2300',
            'account_type' => 'Current Liabilities',
            'detail_type' => 'Duties and Taxes',
        ],
        [
            'code' => '2312',
            'name' => 'VAT Payable',
            'type' => 'liability',
            'parent_code' => '2300',
            'account_type' => 'Current Liabilities',
            'detail_type' => 'Duties and Taxes',
        ],
    ];

    public function up(): void
    {
        $accountTypeIds = DB::table('account_types')
            ->where('is_active', true)
            ->pluck('id', 'name');

        $detailTypeIds = DB::table('detail_types as dt')
            ->join('account_types as at', 'at.id', '=', 'dt.account_type_id')
            ->where('dt.is_active', true)
            ->where('at.is_active', true)
            ->select([
                'dt.id',
                'dt.name as detail_name',
                'at.name as account_type_name',
            ])
            ->get()
            ->mapWithKeys(fn ($row) => [$row->account_type_name . '|' . $row->detail_name => (int) $row->id]);

        Company::query()->orderBy('id')->chunk(100, function ($companies) use ($accountTypeIds, $detailTypeIds): void {
            foreach ($companies as $company) {
                $this->backfillCompanyAccounts($company, $accountTypeIds->all(), $detailTypeIds->all());
            }
        });
    }

    public function down(): void
    {
        // This is a forward-only compatibility/data migration.
    }

    /**
     * @param array<string, int> $accountTypeIds
     * @param array<string, int> $detailTypeIds
     */
    private function backfillCompanyAccounts(Company $company, array $accountTypeIds, array $detailTypeIds): void
    {
        $companyId = (string) $company->getKey();

        $withholdingPayable = Account::query()
            ->where('company_id', $companyId)
            ->where('code', '2310')
            ->first();

        if ($withholdingPayable instanceof Account && $withholdingPayable->name === 'TDS Payable') {
            $withholdingPayable->forceFill([
                'name' => 'Withholding Tax Payable',
                'account_type_id' => $accountTypeIds['Current Liabilities'] ?? $withholdingPayable->account_type_id,
                'detail_type_id' => $detailTypeIds['Current Liabilities|Duties and Taxes'] ?? $withholdingPayable->detail_type_id,
                'is_system' => true,
                'status' => 'active',
            ])->save();
        }

        foreach (self::TAX_ACCOUNTS as $definition) {
            $parent = Account::query()
                ->where('company_id', $companyId)
                ->where('code', $definition['parent_code'])
                ->first();

            if (!$parent instanceof Account) {
                continue;
            }

            $account = Account::query()
                ->where('company_id', $companyId)
                ->where('code', $definition['code'])
                ->first();

            if ($account instanceof Account) {
                continue;
            }

            Account::query()->create([
                'company_id' => $companyId,
                'tenant_id' => $companyId,
                'code' => $definition['code'],
                'name' => $definition['name'],
                'type' => $definition['type'],
                'account_type_id' => $accountTypeIds[$definition['account_type']] ?? null,
                'detail_type_id' => $detailTypeIds[$definition['account_type'] . '|' . $definition['detail_type']] ?? null,
                'parent_id' => $parent->getKey(),
                'is_posting' => true,
                'status' => 'active',
                'contra_account_id' => null,
                'description' => null,
                'is_system' => true,
            ]);
        }

        $updates = [];

        foreach (self::DEFAULT_ACCOUNT_CODES as $column => $code) {
            if (!empty($company->{$column})) {
                continue;
            }

            $account = Account::query()
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->first();

            if ($account instanceof Account) {
                $updates[$column] = (string) $account->getKey();
            }
        }

        if ($updates !== []) {
            $company->forceFill($updates)->save();
        }
    }
};

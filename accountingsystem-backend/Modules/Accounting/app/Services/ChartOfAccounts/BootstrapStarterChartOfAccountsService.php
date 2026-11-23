<?php

namespace Modules\Accounting\Services\ChartOfAccounts;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Services\CurrentTenantContext;
use Modules\Accounting\Support\GeneralBusinessChartOfAccountsTemplate;
use Modules\Users\Models\Company;
use RuntimeException;

final class BootstrapStarterChartOfAccountsService
{
    private const DEFAULT_COMPANY_ACCOUNT_CODES = [
        'default_accounts_receivable_account_id' => '1310',
        'default_sales_income_account_id' => '4110',
        'default_inventory_asset_account_id' => '1410',
        'default_cogs_account_id' => '5111',
        'default_sales_tax_payable_account_id' => '2311',
        'default_cash_receipt_account_id' => '1110',
        'default_check_receipt_account_id' => '1210',
        'default_bank_receipt_account_id' => '1210',
        'default_online_receipt_account_id' => '1230',
    ];

    private const NORMAL_BALANCE_MAP = [
        'asset' => 'debit',
        'expense' => 'debit',
        'liability' => 'credit',
        'equity' => 'credit',
        'revenue' => 'credit',
    ];

    /**
     * @var array<string, int>|null
     */
    private ?array $accountTypeIdsByName = null;

    /**
     * @var array<string, int>|null
     */
    private ?array $detailTypeIdsByName = null;

    public function __construct(
        private readonly CurrentTenantContext $currentTenantContext
    ) {}

    public function createStarterAccounts(): int
    {
        $tenantId = $this->currentTenantContext->companyId();
        $byCode = [];
        $canonicalEntries = GeneralBusinessChartOfAccountsTemplate::entries();

        DB::transaction(function () use ($tenantId, $canonicalEntries, &$byCode): void {
            $this->ensureStarterLookupTypes($canonicalEntries);

            foreach ($canonicalEntries as $entry) {
                $type = (string) $entry['type'];
                $code = (string) $entry['code'];
                $accountTypeName = (string) ($entry['account_type'] ?? '');
                $detailTypeName = (string) ($entry['detail_type'] ?? '');

                $accountTypeId = $accountTypeName !== '' ? $this->resolveAccountTypeId($accountTypeName) : null;
                $detailTypeId = $detailTypeName !== '' ? $this->resolveDetailTypeId($accountTypeName, $detailTypeName) : null;

                $parentCode = $entry['parent_code'] ?? null;
                $parentId = null;
                if ($parentCode !== null) {
                    $parent = $byCode[(string) $parentCode] ?? null;
                    if (! $parent) {
                        $parent = Account::query()
                            ->where('company_id', $tenantId)
                            ->where('code', (string) $parentCode)
                            ->first();
                    }

                    if (! $parent) {
                        throw new RuntimeException(sprintf(
                            'Cannot resolve parent account [%s] for starter account [%s].',
                            $parentCode,
                            $code
                        ));
                    }

                    $parentId = $parent->id;
                }

                $contraCode = $entry['contra_account_code'] ?? null;
                $contraAccount = $contraCode !== null
                    ? ($byCode[(string) $contraCode] ?? null)
                    : null;

                $account = Account::query()->updateOrCreate(
                    [
                        'company_id' => $tenantId,
                        'tenant_id' => $tenantId,
                        'code' => $code,
                    ],
                    [
                        'name' => (string) $entry['name'],
                        'type' => $type,
                        'normal_balance' => $this->normalBalanceFromType($type),
                        'parent_id' => $parentId,
                        'is_posting' => (bool) $entry['is_posting'],
                        'is_contra' => (bool) ($entry['is_contra'] ?? false),
                        'contra_account_id' => $contraAccount?->getKey(),
                        'status' => 'active',
                        'account_type_id' => $accountTypeId,
                        'detail_type_id' => $detailTypeId,
                        'is_system' => true,
                        'description' => $entry['description'] ?? null,
                    ]
                );

                $byCode[$code] = $account;
            }

            $this->assignStarterDefaults($tenantId, $byCode);
        });

        return count($canonicalEntries);
    }

    /**
     * Add the general-business lookup catalog without changing company accounts.
     * Existing installations may only have the church/farm lookup types seeded.
     *
     * @param list<array<string, mixed>> $entries
     */
    private function ensureStarterLookupTypes(array $entries): void
    {
        foreach ($entries as $entry) {
            $typeName = $entry['account_type'] ?? null;
            if (! $typeName) {
                continue;
            }

            $typeCode = \Illuminate\Support\Str::slug($typeName);
            DB::table('account_types')->updateOrInsert(['code' => $typeCode], [
                'name' => $typeName,
                'core_type' => $entry['type'],
                'is_active' => true,
                'updated_at' => now(),
            ]);
            $typeId = DB::table('account_types')->where('code', $typeCode)->value('id');
            $detailName = $entry['detail_type'] ?? null;
            if ($detailName) {
                DB::table('detail_types')->updateOrInsert([
                    'account_type_id' => $typeId,
                    'code' => \Illuminate\Support\Str::slug($detailName),
                ], [
                    'name' => $detailName,
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            }
        }

        $this->accountTypeIdsByName = null;
        $this->detailTypeIdsByName = null;
    }

    private function normalBalanceFromType(string $type): string
    {
        if (! isset(self::NORMAL_BALANCE_MAP[$type])) {
            throw new RuntimeException(sprintf('Starter chart template uses unsupported account type [%s].', $type));
        }

        return self::NORMAL_BALANCE_MAP[$type];
    }

    /**
     * @param  array<string, Account>  $accountsByCode
     */
    private function assignStarterDefaults(string $companyId, array $accountsByCode): void
    {
        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            return;
        }

        $updates = [];

        foreach (self::DEFAULT_COMPANY_ACCOUNT_CODES as $column => $code) {
            if (! empty($company->{$column})) {
                continue;
            }

            $account = $accountsByCode[$code] ?? null;

            if ($account instanceof Account) {
                $updates[$column] = (string) $account->getKey();
            }
        }

        if ($updates === []) {
            return;
        }

        $company->forceFill($updates)->save();
    }

    private function resolveAccountTypeId(string $accountTypeName): int
    {
        $accountTypeId = $this->accountTypeIdsByName()[$accountTypeName] ?? null;

        if (! $accountTypeId) {
            throw new RuntimeException(sprintf('Starter chart template references unknown account type [%s].', $accountTypeName));
        }

        return $accountTypeId;
    }

    private function resolveDetailTypeId(string $accountTypeName, string $detailTypeName): int
    {
        $key = $accountTypeName.'|'.$detailTypeName;
        $detailTypeId = $this->detailTypeIdsByName()[$key] ?? null;

        if (! $detailTypeId) {
            throw new RuntimeException(sprintf(
                'Starter chart template references unknown detail type [%s] for account type [%s].',
                $detailTypeName,
                $accountTypeName
            ));
        }

        return $detailTypeId;
    }

    /**
     * @return array<string, int>
     */
    private function accountTypeIdsByName(): array
    {
        if ($this->accountTypeIdsByName !== null) {
            return $this->accountTypeIdsByName;
        }

        /** @var array<string, int> $map */
        $map = DB::table('account_types')
            ->where('is_active', true)
            ->pluck('id', 'name')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->accountTypeIdsByName = $map;

        return $this->accountTypeIdsByName;
    }

    /**
     * @return array<string, int>
     */
    private function detailTypeIdsByName(): array
    {
        if ($this->detailTypeIdsByName !== null) {
            return $this->detailTypeIdsByName;
        }

        $rows = DB::table('detail_types as dt')
            ->join('account_types as at', 'at.id', '=', 'dt.account_type_id')
            ->where('dt.is_active', true)
            ->where('at.is_active', true)
            ->select([
                'dt.id',
                'dt.name as detail_name',
                'at.name as account_type_name',
            ])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->account_type_name.'|'.$row->detail_name] = (int) $row->id;
        }

        $this->detailTypeIdsByName = $map;

        return $this->detailTypeIdsByName;
    }
}

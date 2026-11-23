<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Support\CanonicalChartOfAccountsTemplate;

class AccountSeeder extends Seeder
{
    private const NORMAL_BALANCE_MAP = [
        'asset' => 'debit',
        'expense' => 'debit',
        'liability' => 'credit',
        'equity' => 'credit',
        'revenue' => 'credit',
    ];

    private function normalBalanceFromType(string $type): string
    {
        if (! isset(self::NORMAL_BALANCE_MAP[$type])) {
            throw new \InvalidArgumentException("Invalid account type: {$type}");
        }

        return self::NORMAL_BALANCE_MAP[$type];
    }

    public function run(): void
    {
        $accountTypeRows = DB::table('account_types')
            ->where('is_active', true)
            ->select(['id', 'name'])
            ->get();

        $accountTypeIdByName = [];
        foreach ($accountTypeRows as $row) {
            $accountTypeIdByName[(string) $row->name] = (int) $row->id;
        }

        $detailTypeRows = DB::table('detail_types as dt')
            ->join('account_types as at', 'at.id', '=', 'dt.account_type_id')
            ->where('dt.is_active', true)
            ->where('at.is_active', true)
            ->select(['dt.id', 'dt.name as detail_name', 'at.name as account_type_name'])
            ->get();

        $detailTypeIdByTypeAndName = [];
        foreach ($detailTypeRows as $row) {
            $detailTypeIdByTypeAndName[(string) $row->account_type_name][(string) $row->detail_name] = (int) $row->id;
        }

        $byCode = [];

        DB::transaction(function () use ($accountTypeIdByName, $detailTypeIdByTypeAndName, &$byCode): void {
            $canonicalEntries = CanonicalChartOfAccountsTemplate::entries();

            foreach ($canonicalEntries as $row) {
                $type = (string) $row['type'];
                $code = (string) $row['code'];

                $accountTypeName = (string) ($row['account_type'] ?? '');
                $detailTypeName = (string) ($row['detail_type'] ?? '');

                $accountTypeId = null;
                $detailTypeId = null;

                if ($accountTypeName !== '') {
                    $accountTypeId = $accountTypeIdByName[$accountTypeName] ?? null;
                    if (! $accountTypeId) {
                        throw new \RuntimeException("Missing account type lookup for account {$code}: {$accountTypeName}");
                    }
                }

                if ($detailTypeName !== '') {
                    $detailTypeId = $detailTypeIdByTypeAndName[$accountTypeName][$detailTypeName] ?? null;
                    if (! $detailTypeId) {
                        throw new \RuntimeException("Missing detail type lookup for account {$code}: {$accountTypeName} / {$detailTypeName}");
                    }
                }

                $parentCode = $row['parent_code'] ?? null;
                $parentId = null;
                if ($parentCode !== null) {
                    $parent = $byCode[(string) $parentCode] ?? null;
                    if (! $parent) {
                        $parent = Account::query()->where('code', (string) $parentCode)->first();
                    }
                    if (! $parent) {
                        throw new \RuntimeException("Cannot resolve parent account {$parentCode} for account {$code}");
                    }
                    $parentId = $parent->id;
                }

                $contraCode = $row['contra_account_code'] ?? null;
                $contraAccount = $contraCode !== null
                    ? ($byCode[(string) $contraCode] ?? null)
                    : null;

                $account = Account::updateOrCreate(
                    [
                        'company_id' => null,
                        'tenant_id' => null,
                        'code' => $code,
                    ],
                    [
                        'name' => (string) $row['name'],
                        'type' => $type,
                        'normal_balance' => $this->normalBalanceFromType($type),
                        'parent_id' => $parentId,
                        'is_posting' => (bool) $row['is_posting'],
                        'is_contra' => (bool) ($row['is_contra'] ?? false),
                        'contra_account_id' => $contraAccount?->getKey(),
                        'status' => 'active',
                        'account_type_id' => $accountTypeId,
                        'detail_type_id' => $detailTypeId,
                        'is_system' => true,
                        'description' => $row['description'] ?? null,
                    ]
                );

                $byCode[$code] = $account;
            }

            $canonicalCodes = array_column($canonicalEntries, 'code');

            $staleSystemAccounts = Account::query()
                ->whereNull('company_id')
                ->whereNull('tenant_id')
                ->where('is_system', true)
                ->whereNotIn('code', $canonicalCodes)
                ->get();

            foreach ($staleSystemAccounts as $staleAccount) {
                $hasJournalReference = DB::table('journal_items')
                    ->where('account_id', $staleAccount->id)
                    ->exists();

                if ($hasJournalReference) {
                    $staleAccount->update([
                        'status' => 'inactive',
                    ]);

                    continue;
                }

                Account::query()
                    ->where('parent_id', $staleAccount->id)
                    ->update(['parent_id' => null]);

                Account::query()
                    ->where('contra_account_id', $staleAccount->id)
                    ->update([
                        'is_contra' => false,
                        'contra_account_id' => null,
                    ]);

                $staleAccount->delete();
            }
        });
    }
}

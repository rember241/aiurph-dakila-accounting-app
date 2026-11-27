<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalItem;

final class AccountingDashboardQueryService
{
    private const BALANCE_GROUPS = [
        'cashBalance' => ['asset', ['cash-in-hand', 'cash-on-hand', 'petty-cash', 'bank-accounts',
            'bank-account', 'cash-in-bank', 'cash-on-hand-and-in-banks', 'cash-and-cash-equivalents']],
        'receivables' => ['asset', ['accounts-receivable', 'receivables']],
        'payables' => ['liability', ['accounts-payable', 'payables']],
    ];

    public function overview(string $companyId, string $date): array
    {
        $end = Carbon::parse($date);
        $start = $end->copy()->startOfMonth()->subMonths(5);
        $firstDate = $start->toDateString();
        $accounts = Account::query()
            ->leftJoin('detail_types', 'detail_types.id', '=', 'accounts.detail_type_id')
            ->where('accounts.company_id', $companyId)
            ->get([
                'accounts.id', 'accounts.name', 'accounts.parent_id', 'accounts.type',
                'accounts.normal_balance', 'accounts.is_posting', 'detail_types.code as detail_code',
            ])
            ->keyBy('id');

        $accountGroups = [];
        $balances = array_fill_keys(array_keys(self::BALANCE_GROUPS), null);
        $normalBalances = [];

        foreach ($accounts->where('is_posting', true) as $account) {
            $group = match ($account->type) {
                'revenue' => 'totalIncome',
                'expense' => 'totalExpenses',
                default => $this->balanceGroup($account, $accounts),
            };

            if ($group === null) {
                continue;
            }

            $accountGroups[$account->id] = $group;

            if (array_key_exists($group, $balances)) {
                $balances[$group] = '0.00';
            }

            $normalBalances[$account->id] = match ($group) {
                'cashBalance', 'receivables' => 'debit',
                'payables' => 'credit',
                default => $account->normal_balance,
            };
        }

        // Collect all opening balances into one bucket, followed by one bucket per calendar month.
        $bucket = "CASE WHEN journal_entries.entry_date < ? THEN 'opening' ELSE substr(CAST(journal_entries.entry_date AS VARCHAR), 1, 7) END";
        $movements = JournalItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '<=', $date)
            ->whereIn('journal_items.account_id', array_keys($accountGroups))
            ->select('journal_items.account_id')
            ->selectRaw("$bucket as period", [$firstDate])
            ->selectRaw('SUM(CASE WHEN journal_items.type = ? THEN journal_items.amount ELSE -journal_items.amount END) as debit_balance', ['debit'])
            ->groupBy('journal_items.account_id', 'period')
            ->get();

        $totals = [];
        $rankings = ['totalIncome' => [], 'totalExpenses' => []];
        $currentPeriod = $end->format('Y-m');

        foreach ($movements as $movement) {
            $accountId = $movement->account_id;
            $group = $accountGroups[$accountId];
            $amount = (string) $movement->debit_balance;

            if ($normalBalances[$accountId] === 'credit') {
                $amount = bcsub('0', $amount, 2);
            }

            if ($movement->period === $currentPeriod && isset($rankings[$group]) && bccomp($amount, '0', 2) > 0) {
                $rankings[$group][] = ['id' => $accountId, 'name' => $accounts[$accountId]->name, 'amount' => bcadd($amount, '0', 2)];
            }

            $totals[$movement->period][$group] = bcadd($totals[$movement->period][$group] ?? '0', $amount, 2);
        }

        foreach ($balances as $group => $amount) {
            $balances[$group] = $totals['opening'][$group] ?? $amount;
        }

        $months = [];

        for ($month = $start->copy(); $month <= $end; $month->addMonth()) {
            $period = $month->format('Y-m');
            $totalsForMonth = $totals[$period] ?? [];
            $income = $totalsForMonth['totalIncome'] ?? '0.00';
            $expenses = $totalsForMonth['totalExpenses'] ?? '0.00';

            foreach ($balances as $group => $amount) {
                if ($amount !== null) {
                    $balances[$group] = bcadd($amount, $totalsForMonth[$group] ?? '0', 2);
                }
            }

            $months[] = [
                'month' => $period,
                'totalIncome' => $income,
                'totalExpenses' => $expenses,
                'netProfit' => bcsub($income, $expenses, 2),
                ...$balances,
            ];
        }

        foreach ($rankings as $group => $rows) {
            usort($rows, static fn ($a, $b) => bccomp($b['amount'], $a['amount'], 2)
                ?: strcmp($a['name'], $b['name']) ?: strcmp($a['id'], $b['id']));
            $rankings[$group] = array_slice($rows, 0, 10);
        }

        return ['months' => $months, 'rankings' => $rankings];
    }

    private function balanceGroup(Account $account, Collection $accounts): ?string
    {
        $visited = [];
        $current = $account;

        while ($current && !isset($visited[$current->id])) {
            $visited[$current->id] = true;
            $detail = $current->detail_code;
            $name = Str::slug($current->name);

            foreach (self::BALANCE_GROUPS as $group => [$type, $codes]) {
                if ($account->type === $type && (in_array($detail, $codes, true) || in_array($name, $codes, true))) {
                    return $group;
                }
            }

            $current = $accounts->get($current->parent_id);
        }

        return null;
    }
}

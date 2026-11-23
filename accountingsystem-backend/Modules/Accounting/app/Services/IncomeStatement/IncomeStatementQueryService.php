<?php

namespace Modules\Accounting\Services\IncomeStatement;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Accounting\Exports\IncomeStatementExport;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Services\CurrentTenantContext;
use Modules\Users\Models\Company;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class IncomeStatementQueryService
{
    public function __construct(
        private CurrentTenantContext $currentTenantContext
    ) {}

    /**
     * Returns revenue and expense accounts with their
     * transaction data for the income statement report
     *
     * @param array $params The validated parameters from GenerateIncomeStatementRequest
     */
    public function generateReport(array $params): array
    {
        $companyId = $this->currentTenantContext->companyId();
        $isComparingSpecificPeriod = isset($params['compareWith']) && $params['compareWith'] === 'specific';

        // If client is comparing a specific period, no need to call resolveComparisonPeriods
        // since the comparison period is already defined in the params
        $comparisonPeriods = $isComparingSpecificPeriod
            ? [[
                    'label'     => $this->formatPeriodLabel($params['compareStartDate'], $params['compareEndDate']),
                    'startDate' => $params['compareStartDate'],
                    'endDate'   => $params['compareEndDate'],
                ]]
            : $this->resolveComparisonPeriods($params);
        $numberOfPeriods   = (int) ($params['numberOfPeriods'] ?? 1);
        $accounts          = $this->getAccountLedgerData($params, $companyId, $comparisonPeriods, $numberOfPeriods);

        // Separate accounts
        $revenueAccounts = $accounts->where('type', 'revenue');
        $expenseAccounts = $accounts->where('type', 'expense');

        // Compute totals
        $totalRevenue = $this->computeTotalsArray($revenueAccounts, $comparisonPeriods, $numberOfPeriods, $isComparingSpecificPeriod);
        $totalExpense = $this->computeTotalsArray($expenseAccounts, $comparisonPeriods, $numberOfPeriods, $isComparingSpecificPeriod);
        $netTotal     = collect($totalRevenue)
            ->map(fn ($value, $index) => bcsub($value, $totalExpense[$index], 2))
            ->values()
            ->all();

        // Build date labels array
        $labels = $this->buildLabels($params, $comparisonPeriods, $numberOfPeriods, $isComparingSpecificPeriod);

        return [
            'labels'       => $labels,
            'accounts'     => $accounts->values(),
            'totalRevenue' => $totalRevenue,
            'totalExpense' => $totalExpense,
            'netTotal'     => $netTotal,
        ];
    }

    /**
     * Returns a chosen file representation of the Income Statement report
     *
     * @param array $params The validated parameters from DownloadIncomeStatementRequest
     * @return Response|BinaryFileResponse
     */
    public function downloadReport(array $params): Response|BinaryFileResponse
    {
        $companyId         = $this->currentTenantContext->companyId();
        $isComparingSpecificPeriod = isset($params['compareWith']) && $params['compareWith'] === 'specific';

        $comparisonPeriods = $isComparingSpecificPeriod
            ? [
                [
                    'label'     => $this->formatPeriodLabel($params['compareStartDate'], $params['compareEndDate']),
                    'startDate' => $params['compareStartDate'],
                    'endDate'   => $params['compareEndDate'],
                ]
            ]
            : $this->resolveComparisonPeriods($params);
        $numberOfPeriods   = (int) ($params['numberOfPeriods'] ?? 1);
        $accounts           = $this->getAccountLedgerData($params, $companyId, $comparisonPeriods, $numberOfPeriods);

        // Separate accounts
        $revenueAccounts = $accounts->where('type', 'revenue');
        $expenseAccounts = $accounts->where('type', 'expense');

        // Compute totals
        $totalRevenue = $this->computeTotalsArray($revenueAccounts, $comparisonPeriods, $numberOfPeriods, $isComparingSpecificPeriod);
        $totalExpense = $this->computeTotalsArray($expenseAccounts, $comparisonPeriods, $numberOfPeriods, $isComparingSpecificPeriod);
        $netTotal     = collect($totalRevenue)
            ->map(fn ($value, $index) => bcsub($value, $totalExpense[$index], 2))
            ->values()
            ->all();

        // Build date labels array
        $labels = $this->buildLabels($params, $comparisonPeriods, $numberOfPeriods, $isComparingSpecificPeriod);

        return match ($params['fileType']) {
            'pdf' => Pdf::loadView('accounting::income-statement.pdf', [
                'companyName'      => Company::find($companyId)?->name ?? '',
                'labels'           => $labels,
                'revenueAccounts'  => $revenueAccounts->toArray(),
                'expenseAccounts'  => $expenseAccounts->toArray(),
                'totalRevenue'     => $totalRevenue,
                'totalExpense'     => $totalExpense,
                'netTotal'         => $netTotal,
            ])
                ->setPaper('a4', count($labels) <= 3 ? 'portrait' : 'landscape')
                ->setOptions(['enable_php' => true])
                ->download(),

            'csv', 'xlsx' => Excel::download(new IncomeStatementExport(
                $params['fileType'],
                Company::find($companyId)?->name ?? '',
                $labels,
                $revenueAccounts->toArray(),
                $expenseAccounts->toArray(),
                $totalRevenue,
                $totalExpense,
                $netTotal,
            ), "income-statement.{$params['fileType']}"),
        };
    }

    /**
     * Resolves comparison periods based on compareWith and numberOfPeriods params.
     * Returns an empty array if compareWith is null.
     *
     * Each entry contains:
     * - label: Human-readable period label e.g. "March 2026"
     * - startDate: Start date of the comparison period
     * - endDate: End date of the comparison period
     */
    private function resolveComparisonPeriods(array $params): array
    {
        $compareWith     = $params['compareWith'] ?? null;
        $numberOfPeriods = (int) ($params['numberOfPeriods'] ?? 1);

        if (!$compareWith) return [];

        $periods    = [];
        $startDate  = $params['startDate'];
        $endDate    = $params['endDate'];

        for ($i = 0; $i < $numberOfPeriods; $i++) {
            $period = match ($compareWith) {
                'period'     => $this->getPreviousPeriodDates($startDate, $endDate),
                'fiscalYear' => $this->getSamePeriodLastYear($startDate, $endDate, $i + 1),
            };

            $periods[] = [
                'label'     => $this->formatPeriodLabel($period['startDate'], $period['endDate']),
                'startDate' => $period['startDate'],
                'endDate'   => $period['endDate'],
            ];

            // For period comparison, each iteration steps back one more period
            if ($compareWith === 'period') {
                $startDate = $period['startDate'];
                $endDate   = $period['endDate'];
            }
        }

        return $periods;
    }

    /**
     * Computes the previous period date range based on the passed in date range
     */
    private function getPreviousPeriodDates(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end   = Carbon::parse($endDate);

        if ($start->isStartOfYear() && $end->isEndOfYear()) {
            return [
                'startDate' => $start->copy()->subYear()->startOfYear()->toDateString(),
                'endDate'   => $start->copy()->subYear()->endOfYear()->toDateString(),
            ];
        }

        if ($start->isStartOfMonth() && $end->isEndOfMonth()) {
            return [
                'startDate' => $start->copy()->subMonth()->startOfMonth()->toDateString(),
                'endDate'   => $start->copy()->subMonth()->endOfMonth()->toDateString(),
            ];
        }

        $periodLengthInDays = $start->diffInDays($end);

        return [
            'startDate' => $start->copy()->subDays($periodLengthInDays + 1)->toDateString(),
            'endDate'   => $start->copy()->subDay()->toDateString(),
        ];
    }

    /**
     * Returns the same date range shifted back by N fiscal years
     */
    private function getSamePeriodLastYear(string $startDate, string $endDate, int $yearsBack): array
    {
        return [
            'startDate' => Carbon::parse($startDate)->subYears($yearsBack)->toDateString(),
            'endDate'   => Carbon::parse($endDate)->subYears($yearsBack)->toDateString(),
        ];
    }

    /**
     * Formats a date range into a human-readable label
     */
    private function formatPeriodLabel(string $startDate, string $endDate): string
    {
        $start = Carbon::parse($startDate);
        $end   = Carbon::parse($endDate);

        // Full month e.g. "April 2026"
        if ($start->isStartOfMonth() && $end->isEndOfMonth() && $start->isSameMonth($end)) {
            return $start->format('F Y');
        }

        // Full year e.g. "2026"
        if ($start->isStartOfYear() && $end->isEndOfYear()) {
            return $start->format('Y');
        }

        // Custom range e.g. "Apr 1, 2026 - Apr 15, 2026"
        return $start->toFormattedDateString() . ' - ' . $end->toFormattedDateString();
    }

    /**
     * Retrieves the transaction data of all accounts within the specified date range
     */
    private function getAccountLedgerData(array $params, string $companyId, array $comparisonPeriods, int $numberOfPeriods): Collection
    {
        $comparisonBalances = collect($comparisonPeriods)
            ->map(fn ($period) => $this->getPeriodBalances(
                $companyId,
                $params['accountIds'],
                $period['startDate'],
                $period['endDate'],
            ));

        return Account::query()
            ->where('accounts.company_id', $companyId)
            ->whereIn('accounts.id', $params['accountIds'])
            ->select('accounts.id', 'accounts.name', 'accounts.code', 'accounts.normal_balance', 'accounts.type', 'accounts.is_contra')
            ->leftJoin('journal_items', 'accounts.id', '=', 'journal_items.account_id')
            ->leftJoin('journal_entries', function ($join) use ($companyId, $params) {
                $join->on('journal_items.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.company_id', $companyId)
                    ->where('journal_entries.status', 'posted')
                    ->whereBetween('journal_entries.entry_date', [$params['startDate'], $params['endDate']]);
            })
            ->groupBy('accounts.id', 'accounts.name', 'accounts.code', 'accounts.normal_balance', 'accounts.type', 'accounts.is_contra')
            ->selectRaw('
            SUM(CASE
                WHEN journal_items.type = ?
                AND journal_entries.id IS NOT NULL
                THEN journal_items.amount ELSE 0
            END) as total_debit,
            SUM(CASE
                WHEN journal_items.type = ?
                AND journal_entries.id IS NOT NULL
                THEN journal_items.amount ELSE 0
            END) as total_credit
        ', ['debit', 'credit'])
            ->orderBy('accounts.name')
            ->get()
            ->map(function ($account) use ($companyId, $params, $comparisonPeriods, $comparisonBalances, $numberOfPeriods) {
                $transactions = $this->getTransactions(
                    $companyId,
                    $account->id,
                    $params['startDate'],
                    $params['endDate']
                );

                $debit   = (string) ($account->total_debit ?? '0.0');
                $credit  = (string) ($account->total_credit ?? '0.0');
                $balance = $account->normal_balance === 'debit'
                    ? bcsub($debit, $credit, 2)
                    : bcsub($credit, $debit, 2);

                // Build previousBalances from most recent to oldest
                $previousBalances = $comparisonBalances
                    ->map(fn ($periodBalances) => $periodBalances->get($account->id) ?? '0.0')
                    ->values()
                    ->all();

                // Append variance if numberOfPeriods === 1 or if comparing with a specific period
                if (($numberOfPeriods === 1 || $params['compareWith'] === 'specific') && !empty($previousBalances))
                    $previousBalances[] = bcsub($balance, $previousBalances[0], 2);

                $result = [
                    'id'              => $account->id,
                    'code'            => $account->code,
                    'name'            => $account->name,
                    'type'            => $account->type,
                    'normalBalance'   => $account->normal_balance,
                    'isContra'        => $account->is_contra,
                    'balance'         => $balance,
                    'transactions'    => $this->attachRunningBalance($transactions, $account),
                ];

                if (!empty($comparisonPeriods))
                    $result['previousBalances'] = $previousBalances;

                return $result;
            })
            ->filter(function (array $account) {
                $hasBalance = abs((float) $account['balance']) >= 0.01;
                $hasPreviousBalance = collect($account['previousBalances'] ?? [])
                    ->contains(fn ($value) => abs((float) $value) >= 0.01);

                return $hasBalance || $hasPreviousBalance;
            })
            ->values();
    }

    /**
     * Fetches balances for all accounts within a
     * specific date range keyed by account ID
     */
    private function getPeriodBalances(
        string $companyId,
        array  $accountIds,
        string $startDate,
        string $endDate
    ): Collection {
        return JournalItem::query()
            ->whereIn('journal_items.account_id', $accountIds)
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
            ->groupBy('journal_items.account_id', 'accounts.normal_balance')
            ->select('journal_items.account_id', 'accounts.normal_balance')
            ->selectRaw('
                SUM(CASE WHEN journal_items.type = ? THEN journal_items.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN journal_items.type = ? THEN journal_items.amount ELSE 0 END) as total_credit
            ', ['debit', 'credit'])
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->account_id => $row->normal_balance === 'debit'
                    ? bcsub((string) $row->total_debit, (string) $row->total_credit)
                    : bcsub((string) $row->total_credit, (string) $row->total_debit)
            ]);
    }

    /**
     * Fetches the posted journal entries that reference the account ID sorted by date ascending
     */
    private function getTransactions(string $companyId, string $accountId, string $startDate, string $endDate): Collection
    {
        return JournalItem::query()
            ->where('journal_items.account_id', $accountId)
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
            ->select(
                'journal_entries.id',
                'journal_entries.entry_date',
                'journal_entries.prefix',
                'journal_entries.entry_number',
                'journal_items.description',
                'journal_items.type',
                'journal_items.amount',
            )
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.created_at')
            ->get();
    }

    /**
     * Computes the running balance of an account
     */
    private function attachRunningBalance(Collection $transactions, Account $account): Collection
    {
        $runningBalance = '0.0';

        return $transactions->map(function ($transaction) use (&$runningBalance, $account) {
            $debit  = $transaction->type === 'debit'  ? (string) $transaction->amount : '0.0';
            $credit = $transaction->type === 'credit' ? (string) $transaction->amount : '0.0';

            $account->normal_balance === 'debit'
                ? $runningBalance = bcadd($runningBalance, bcsub($debit, $credit, 2), 2)
                : $runningBalance = bcadd($runningBalance, bcsub($credit, $debit, 2), 2) ;

            return [
                'id'            => $transaction->id,
                'date'          => $transaction->entry_date,
                'journalNumber' => $transaction->prefix . '-' . str_pad($transaction->entry_number, 4, '0', STR_PAD_LEFT),
                'description'   => $transaction->description ?? '',
                'debit'         => $debit,
                'credit'        => $credit,
                'balance'       => $runningBalance,
            ];
        });
    }

    /**
     * Builds the labels array for the response
     * Index 0 is always the current period, followed by comparison periods,
     * with Variance appended last if numberOfPeriods === 1 or if
     * comparing with a specific period
     */
    private function buildLabels(
        array $params,
        array $comparisonPeriods,
        int $numberOfPeriods,
        bool $isComparingSpecificPeriod
    ): array
    {
        $labels = [
            $this->formatPeriodLabel($params['startDate'], $params['endDate']),
            ...array_column($comparisonPeriods, 'label'),
        ];

        if (($numberOfPeriods === 1 || $isComparingSpecificPeriod) && !empty($comparisonPeriods))
            $labels[] = 'Variance';

        return $labels;
    }

    /**
     * Computes the totals array for a group of accounts (revenue or expense)
     * Index 0 is the current period total, followed by previous period totals,
     * with variance appended last if numberOfPeriods === 1 or if comparing
     * with a specific period
     */
    private function computeTotalsArray(
        Collection $accounts,
        array $comparisonPeriods,
        int $numberOfPeriods,
        bool $isComparingSpecificPeriod
    ): array
    {
        // Current period total
        $currentTotal = $accounts->reduce(
            fn ($sum, $account) => bcadd($sum, $account['balance'], 2),
            '0.00'
        );

        $totals = [$currentTotal];

        // Previous period totals — one entry per comparison period
        $numberOfComparisons = count($comparisonPeriods);
        for ($i = 0; $i < $numberOfComparisons; $i++) {
            $periodTotal = $accounts->reduce(function ($sum, $account) use ($i) {
                $previousBalance = $account['previousBalances'][$i] ?? '0.0';

                // Exclude variance from the sum — it's always the last element when numberOfPeriods === 1
                return bcadd($sum, $previousBalance, 2);
            }, '0.00');

            $totals[] = $periodTotal;
        }

        // Append variance if numberOfPeriods === 1
        if ($numberOfPeriods === 1 && !empty($comparisonPeriods))
            $totals[] = bcsub($currentTotal, $totals[1], 2);

        return $totals;
    }
}

<?php

namespace Modules\Accounting\Services\BalanceSheet;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Accounting\Exports\BalanceSheetExport;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Services\CurrentTenantContext;
use Modules\Users\Models\Company;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class BalanceSheetQueryService
{
    public function __construct(
        private CurrentTenantContext $currentTenantContext
    ) {}

    /**
     *  Returns asset, liability, and equity accounts with
     *  their transaction data for the balance sheet report
     *
     * @param array $params Validated query params from GenerateBalanceSheetRequest
     * @return array
     */
    public function generateReport(array $params): array
    {
        $companyId = $this->currentTenantContext->companyId();
        $isComparingSpecificDate = isset($params['compareWith']) && $params['compareWith'] === 'specific';

        // If client is comparing a specific date, no need to call resolveComparisonDates
        // since the comparison date is already defined in the params
        $comparisonDates   = $isComparingSpecificDate
            ? [[
                'label' => Carbon::parse($params['compareDate'])->format('F d, Y'),
                'date'  => $params['compareDate'],
            ]]
            : $this->resolveComparisonDates($params);
        $numberOfPeriods   = (int) ($params['numberOfPeriods'] ?? 1);
        $accounts          = $this->getAccountLedgerData($params, $companyId, $comparisonDates, $numberOfPeriods);

        return $this->buildResponse($params, $accounts, $comparisonDates, $numberOfPeriods, $isComparingSpecificDate);
    }

    /**
     * Returns a chosen file representation of the Balance Sheet report
     *
     * @param array $params Validated parameters from DownloadBalanceSheetRequest
     * @return Response|BinaryFileResponse
     */
    public function downloadReport(array $params): Response|BinaryFileResponse
    {
        $companyId       = $this->currentTenantContext->companyId();
        $isComparingSpecificDate = isset($params['compareWith']) && $params['compareWith'] === 'specific';

        $comparisonDates   = $isComparingSpecificDate
            ? [[
                'label' => Carbon::parse($params['compareDate'])->format('F d, Y'),
                'date'  => $params['compareDate'],
            ]]
            : $this->resolveComparisonDates($params);
        $numberOfPeriods = (int) ($params['numberOfPeriods'] ?? 1);
        $accounts        = $this->getAccountLedgerData($params, $companyId, $comparisonDates, $numberOfPeriods);
        $data            = $this->buildResponse($params, $accounts, $comparisonDates, $numberOfPeriods, $isComparingSpecificDate);

        $dateText = 'As of ' . Carbon::parse($params['date'])->toFormattedDateString();

        $currentAssets          = collect($data['accounts'])->where('accountType', 'currentAsset');
        $nonCurrentAssets       = collect($data['accounts'])->where('accountType', 'nonCurrentAsset');
        $currentLiabilities     = collect($data['accounts'])->where('accountType', 'currentLiability');
        $nonCurrentLiabilities  = collect($data['accounts'])->where('accountType', 'nonCurrentLiability');
        $equityAccounts         = collect($data['accounts'])->where('coreType', 'equity');

        return match ($params['fileType']) {
            'pdf' => Pdf::loadView('accounting::balance-sheet.pdf', [
                'companyName'                => Company::find($companyId)?->name ?? '',
                'dateText'                   => $dateText,
                'labels'                     => $data['labels'],
                'currentAssets'              => $currentAssets->toArray(),
                'nonCurrentAssets'           => $nonCurrentAssets->toArray(),
                'currentLiabilities'         => $currentLiabilities->toArray(),
                'nonCurrentLiabilities'      => $nonCurrentLiabilities->toArray(),
                'equityAccounts'             => $equityAccounts->toArray(),
                'totalCurrentAssets'         => $data['totalCurrentAssets'],
                'totalNonCurrentAssets'      => $data['totalNonCurrentAssets'],
                'totalCurrentLiabilities'    => $data['totalCurrentLiabilities'],
                'totalNonCurrentLiabilities' => $data['totalNonCurrentLiabilities'],
                'totalAssets'                => $data['totalAssets'],
                'totalLiabilities'           => $data['totalLiabilities'],
                'totalEquities'              => $data['totalEquities'],
                'totalLiabilitiesEquities'   => $data['totalLiabilitiesEquities'],
            ]) ->setPaper('a4', count($data['labels']) >= 3 ? 'landscape' : 'portrait')
                ->setOptions(['enable_php' => true])
                ->download(),

            'csv', 'xlsx' => Excel::download(new BalanceSheetExport(
                $params['fileType'],
                Company::find($companyId)?->name ?? '',
                $dateText,
                $data['labels'],
                $currentAssets->toArray(),
                $nonCurrentAssets->toArray(),
                $currentLiabilities->toArray(),
                $nonCurrentLiabilities->toArray(),
                $equityAccounts->toArray(),
                $data['totalCurrentAssets'],
                $data['totalNonCurrentAssets'],
                $data['totalCurrentLiabilities'],
                $data['totalNonCurrentLiabilities'],
                $data['totalAssets'],
                $data['totalLiabilities'],
                $data['totalEquities'],
                $data['totalLiabilitiesEquities'],
            ), "balance-sheet.{$params['fileType']}"),
        };
    }

    /**
     * Resolves the comparison dates based on compareWith and numberOfPeriods params.
     * Returns an empty array if compareWith is null.
     *
     * Each entry contains:
     * - label: Human-readable date label e.g. "May 31, 2025"
     * - date: The comparison date string
     */
    private function resolveComparisonDates(array $params): array
    {
        $compareWith     = $params['compareWith'] ?? null;
        $numberOfPeriods = (int) ($params['numberOfPeriods'] ?? 1);

        if (!$compareWith) return [];

        $dates        = [];
        $originalDate = Carbon::parse($params['date']);

        for ($i = 1; $i <= $numberOfPeriods; $i++) {
            $comparisonDate = match ($compareWith) {
                'period'     => $this->getPreviousPeriodDate($originalDate, $i),
                'fiscalYear' => $originalDate->copy()->subYears($i)->toDateString(),
            };

            $dates[] = [
                'label' => Carbon::parse($comparisonDate)->format('F d, Y'),
                'date'  => $comparisonDate,
            ];
        }

        return $dates;
    }

    /**
     * Steps back N periods from the original date:
     * - End of year  (Dec 31) → N months back, end of that month
     * - End of month          → N months back, end of that month
     * - Sunday                → N weeks back, that Sunday
     * - Any other day         → N days back
     */
    private function getPreviousPeriodDate(Carbon $originalDate, int $steps): string
    {
        // End of year — December 31
        if ($originalDate->isEndOfYear()) {
            return $originalDate->copy()
                ->subMonthsNoOverflow($steps)
                ->endOfMonth()
                ->toDateString();
        }

        // End of month
        if ($originalDate->isEndOfMonth()) {
            return $originalDate->copy()
                ->subMonthsNoOverflow($steps)
                ->endOfMonth()
                ->toDateString();
        }

        // End of week - Sunday
        if ($originalDate->isSunday()) {
            return $originalDate->copy()
                ->subWeeks($steps)
                ->toDateString();
        }

        // Random day
        return $originalDate->copy()
            ->subDays($steps)
            ->toDateString();
    }

    private function getAccountLedgerData(array $params, string $companyId, array $comparisonDates, int $numberOfPeriods): Collection
    {
        // Batch fetch all comparison date balances
        $comparisonBalances = collect($comparisonDates)
            ->map(fn ($period) => $this->getDateBalances(
                $companyId,
                $params['accountIds'],
                $period['date'],
            ));

        return Account::query()
            ->where('accounts.company_id', $companyId)
            ->whereIn('accounts.id', $params['accountIds'])
            ->select('accounts.id', 'accounts.name', 'accounts.code', 'accounts.normal_balance',
                'accounts.type', 'accounts.parent_id', 'accounts.is_contra',
                'account_types.code as account_type_code', 'account_types.name as account_type_name',
                'detail_types.code as detail_type_code', 'detail_types.name as detail_type_name')
            ->leftJoin('account_types', 'account_types.id', '=', 'accounts.account_type_id')
            ->leftJoin('detail_types', 'detail_types.id', '=', 'accounts.detail_type_id')
            ->leftJoin('journal_items', 'accounts.id', '=', 'journal_items.account_id')
            ->leftJoin('journal_entries', function ($join) use ($companyId, $params) {
                $join->on('journal_items.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.company_id', $companyId)
                    ->where('journal_entries.status', 'posted')
                    ->where('journal_entries.entry_date', '<=', $params['date']);
            })
            ->groupBy('accounts.id', 'accounts.name', 'accounts.code', 'accounts.normal_balance',
                'accounts.type', 'accounts.parent_id', 'accounts.is_contra',
                'account_types.code', 'account_types.name', 'detail_types.code', 'detail_types.name')
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
            ->map(function ($account) use ($companyId, $params, $comparisonDates, $comparisonBalances, $numberOfPeriods) {
                $transactions = $this->getTransactions($companyId, $account->id, $params['date']);

                $accountType = $this->resolveAccountType($account);

                $debit   = (string) ($account->total_debit ?? '0.0');
                $credit  = (string) ($account->total_credit ?? '0.0');
                $balance = $account->normal_balance === 'debit'
                    ? bcsub($debit, $credit, 2)
                    : bcsub($credit, $debit, 2);

                // Build previousBalances from most recent to oldest
                $previousBalances = $comparisonBalances
                    ->map(fn ($dateBalances) => $dateBalances->get($account->id) ?? '0.0')
                    ->values()
                    ->all();

                // Append variance if numberOfPeriods === 1 or if comparing with a specific date
                if (($numberOfPeriods === 1 || $params['compareWith'] === 'specific') && !empty($previousBalances))
                    $previousBalances[] = bcsub($balance, $previousBalances[0], 2);

                $result = [
                    'id'            => $account->id,
                    'code'          => $account->code,
                    'name'          => $account->name,
                    'coreType'      => $account->type,
                    'accountType'   => $accountType,
                    'isContra'      => $account->is_contra,
                    'normalBalance' => $account->normal_balance,
                    'balance'       => $balance,
                    'transactions'  => $this->attachRunningBalance($transactions, $account),
                ];

                if (!empty($comparisonDates))
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
     * Fetches balances for all accounts as of a specific date
     */
    private function getDateBalances(string $companyId, array $accountIds, string $date): Collection
    {
        return JournalItem::query()
            ->whereIn('journal_items.account_id', $accountIds)
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '<=', $date)
            ->groupBy('journal_items.account_id', 'accounts.normal_balance')
            ->select('journal_items.account_id', 'accounts.normal_balance')
            ->selectRaw('
                SUM(CASE WHEN journal_items.type = ? THEN journal_items.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN journal_items.type = ? THEN journal_items.amount ELSE 0 END) as total_credit
            ', ['debit', 'credit'])
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->account_id => $row->normal_balance === 'debit'
                    ? bcsub((string) $row->total_debit, (string) $row->total_credit, 2)
                    : bcsub((string) $row->total_credit, (string) $row->total_debit, 2)
            ]);
    }

    private function getTransactions(string $companyId, string $accountId, string $date): Collection
    {
        return JournalItem::where('account_id', $accountId)
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '<=', $date)
            ->select(
                'journal_entries.id',
                'journal_entries.entry_date',
                'journal_entries.prefix',
                'journal_entries.entry_number',
                'journal_items.description',
                'journal_items.type',
                'journal_items.amount',
            )
            ->orderBy('journal_entries.entry_date', 'asc')
            ->orderBy('journal_entries.created_at', 'asc')
            ->get();
    }

    /**
     * Resolves statement presentation groups from stable account lookup metadata.
     *
     * The canonical chart is flat, so parent names cannot determine whether an
     * account is current or non-current. Account type/detail type codes remain
     * valid even when companies rename or rearrange their chart hierarchy.
     */
    private function resolveAccountType(Account $account): string
    {
        $accountType = strtolower(trim((string) ($account->account_type_code ?? $account->account_type_name ?? '')));
        $detailType = strtolower(trim((string) ($account->detail_type_code ?? $account->detail_type_name ?? '')));

        $isNonCurrent = str_contains($accountType, 'non-current')
            || str_contains($accountType, 'noncurrent')
            || str_contains($accountType, 'fixed')
            || in_array($detailType, ['property-plant-and-equipment', 'accumulated-depreciation'], true);
        $isCurrent = !$isNonCurrent && (str_contains($accountType, 'current') || str_contains($detailType, 'current'));

        return match (true) {
            $account->type === 'asset' && $isCurrent => 'currentAsset',
            $account->type === 'asset' && $isNonCurrent => 'nonCurrentAsset',
            $account->type === 'asset' && in_array($detailType, [
                'cash-on-hand-and-in-banks',
                'receivables',
                'prepayments-and-other-current-assets',
                'advances',
            ], true) => 'currentAsset',
            $account->type === 'asset' => 'nonCurrentAsset',
            $account->type === 'liability' && $isNonCurrent => 'nonCurrentLiability',
            $account->type === 'liability' => 'currentLiability',
            default => 'equity',
        };
    }

    private function attachRunningBalance(Collection $transactions, Account $account): Collection
    {
        $runningBalance = '0.0';

        return $transactions->map(function ($transaction) use (&$runningBalance, $account) {
            $debit  = $transaction->type === 'debit'  ? (string) $transaction->amount : '0.0';
            $credit = $transaction->type === 'credit' ? (string) $transaction->amount : '0.0';

            $account->normal_balance === 'debit'
                ? $runningBalance = bcadd($runningBalance, bcsub($debit, $credit, 2), 2)
                : $runningBalance = bcadd($runningBalance, bcsub($credit, $debit, 2), 2);

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
     * Builds the final response array
     */
    private function buildResponse(
        array $params,
        Collection $accounts,
        array $comparisonDates,
        int $numberOfPeriods,
        bool $isComparingSpecificDate
    ): array
    {
        $companyId = $this->currentTenantContext->companyId();
        $currentEarningsTotals = $this->computeCurrentEarningsTotalsArray(
            $companyId,
            $params,
            $comparisonDates,
            $numberOfPeriods,
            $isComparingSpecificDate
        );
        $accounts = $accounts
            ->push($this->buildCurrentEarningsRow($currentEarningsTotals, !empty($comparisonDates)))
            ->values();

        $currentAssets         = $accounts->where('accountType', 'currentAsset');
        $nonCurrentAssets      = $accounts->where('accountType', 'nonCurrentAsset');
        $currentLiabilities    = $accounts->where('accountType', 'currentLiability');
        $nonCurrentLiabilities = $accounts->where('accountType', 'nonCurrentLiability');
        $equityAccounts        = $accounts->where('coreType', 'equity');

        $totalCurrentAssets         = $this->computeTotalsArray($currentAssets, $comparisonDates, $numberOfPeriods, $isComparingSpecificDate);
        $totalNonCurrentAssets      = $this->computeTotalsArray($nonCurrentAssets, $comparisonDates, $numberOfPeriods, $isComparingSpecificDate);
        $totalCurrentLiabilities    = $this->computeTotalsArray($currentLiabilities, $comparisonDates, $numberOfPeriods, $isComparingSpecificDate);
        $totalNonCurrentLiabilities = $this->computeTotalsArray($nonCurrentLiabilities, $comparisonDates, $numberOfPeriods, $isComparingSpecificDate);
        $totalEquities              = $this->computeTotalsArray($equityAccounts, $comparisonDates, $numberOfPeriods, $isComparingSpecificDate);

        $totalAssets              = $this->addTotalsArrays($totalCurrentAssets, $totalNonCurrentAssets);
        $totalLiabilities         = $this->addTotalsArrays($totalCurrentLiabilities, $totalNonCurrentLiabilities);
        $totalLiabilitiesEquities = $this->addTotalsArrays($totalLiabilities, $totalEquities);

        return [
            'labels'                     => $this->buildLabels($params, $comparisonDates, $numberOfPeriods, $isComparingSpecificDate),
            'accounts'                   => $accounts->values(),
            'totalCurrentAssets'         => $totalCurrentAssets,
            'totalNonCurrentAssets'      => $totalNonCurrentAssets,
            'totalCurrentLiabilities'    => $totalCurrentLiabilities,
            'totalNonCurrentLiabilities' => $totalNonCurrentLiabilities,
            'totalAssets'                => $totalAssets,
            'totalLiabilities'           => $totalLiabilities,
            'totalEquities'              => $totalEquities,
            'totalLiabilitiesEquities'   => $totalLiabilitiesEquities,
        ];
    }

    /**
     * Builds the Balance Sheet's temporary equity line for unclosed profit/loss.
     *
     * Revenue and expense accounts are normally closed into equity at period
     * end. Until that closing entry exists, the Balance Sheet still needs to
     * present current earnings as equity so assets equal liabilities + equity.
     *
     * @param array<int, string> $currentEarningsTotals
     * @return array<string, mixed>
     */
    private function buildCurrentEarningsRow(array $currentEarningsTotals, bool $hasComparisons): array
    {
        $row = [
            'id' => '__current_period_earnings',
            'code' => 'CURRENT-EARNINGS',
            'name' => 'Current Period Earnings',
            'coreType' => 'equity',
            'accountType' => 'equity',
            'isContra' => false,
            'normalBalance' => 'credit',
            'balance' => $currentEarningsTotals[0] ?? '0.00',
            'transactions' => collect(),
        ];

        if ($hasComparisons) {
            $row['previousBalances'] = array_slice($currentEarningsTotals, 1);
        }

        return $row;
    }

    /**
     * @param array<int, array{label:string,date:string}> $comparisonDates
     * @return array<int, string>
     */
    private function computeCurrentEarningsTotalsArray(
        string $companyId,
        array $params,
        array $comparisonDates,
        int $numberOfPeriods,
        bool $isComparingSpecificDate
    ): array {
        $currentTotal = $this->computeCurrentEarningsAsOf($companyId, $params['date']);
        $totals = [$currentTotal];

        foreach ($comparisonDates as $period) {
            $totals[] = $this->computeCurrentEarningsAsOf($companyId, $period['date']);
        }

        if (($numberOfPeriods === 1 || $isComparingSpecificDate) && !empty($comparisonDates)) {
            $totals[] = bcsub($currentTotal, $totals[1], 2);
        }

        return $totals;
    }

    private function computeCurrentEarningsAsOf(string $companyId, string $date): string
    {
        $fiscalYearStartDate = FiscalYear::query()
            ->forCompany($companyId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('start_date')
            ->value('start_date');

        $query = JournalItem::query()
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.entry_date', '<=', $date)
            ->whereIn('accounts.type', ['revenue', 'expense']);

        if ($fiscalYearStartDate !== null) {
            $query->whereDate('journal_entries.entry_date', '>=', $fiscalYearStartDate);
        }

        $row = $query
            ->selectRaw("
                SUM(CASE
                    WHEN accounts.type = 'revenue' AND journal_items.type = 'credit' THEN journal_items.amount
                    WHEN accounts.type = 'revenue' AND journal_items.type = 'debit' THEN -journal_items.amount
                    WHEN accounts.type = 'expense' AND journal_items.type = 'debit' THEN -journal_items.amount
                    WHEN accounts.type = 'expense' AND journal_items.type = 'credit' THEN journal_items.amount
                    ELSE 0
                END) as current_earnings
            ")
            ->first();

        return number_format(round((float) ($row?->current_earnings ?? 0), 2), 2, '.', '');
    }

    /**
     * Computes the totals array for a group of accounts.
     * Index 0 is the current total, followed by previous period totals,
     * with variance appended last if numberOfPeriods === 1 or when
     * comparing with a specific date
     */
    private function computeTotalsArray(
        Collection $accounts,
        array $comparisonDates,
        int $numberOfPeriods,
        bool $isComparingSpecificDate
    ): array
    {
        $currentTotal = $accounts->reduce(
            fn ($sum, $account) => bcadd($sum, $account['balance'], 2),
            '0.00'
        );

        $totals = [$currentTotal];

        $numberOfComparisons = count($comparisonDates);
        for ($i = 0; $i < $numberOfComparisons; $i++) {
            $periodTotal = $accounts->reduce(function ($sum, $account) use ($i) {
                $previousBalance = $account['previousBalances'][$i] ?? '0.0';
                return bcadd($sum, $previousBalance, 2);
            }, '0.00');

            $totals[] = $periodTotal;
        }

        if (($numberOfPeriods === 1 || $isComparingSpecificDate) && !empty($comparisonDates))
            $totals[] = bcsub($currentTotal, $totals[1], 2);

        return $totals;
    }

    /**
     * Adds two totals arrays element by element
     */
    private function addTotalsArrays(array $totalArray1, array $totalArray2): array
    {
        return collect($totalArray1)
            ->map(fn($value, $index) => bcadd($value, ($totalArray2[$index] ?? '0.0'), 2))
            ->values()
            ->all();
    }

    /**
     * Builds the labels array
     * Index 0 is always the current date, followed by comparison dates,
     * with Variance appended last if numberOfPeriods === 1 or when
     * comparing with a specific date
     */
    private function buildLabels(
        array $params,
        array $comparisonDates,
        int $numberOfPeriods,
        bool $isComparingSpecificDate
    ): array
    {
        $labels = [
            Carbon::parse($params['date'])->format('F d, Y'),
            ...array_column($comparisonDates, 'label'),
        ];

        if (($numberOfPeriods === 1 || $isComparingSpecificDate) && !empty($comparisonDates))
            $labels[] = 'Variance';

        return $labels;
    }
}

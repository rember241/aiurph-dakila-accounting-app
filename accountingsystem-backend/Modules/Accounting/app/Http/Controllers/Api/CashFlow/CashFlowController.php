<?php

namespace Modules\Accounting\Http\Controllers\Api\CashFlow;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Accounting\Http\Requests\CashFlow\GenerateCashFlowRequest;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Services\CurrentTenantContext;

class CashFlowController extends Controller
{
    public function __construct(
        private readonly CurrentTenantContext $currentTenantContext
    )
    {}

    /**
     * Generate a Cash Flow statement with the requested date range
     */
    public function generate(GenerateCashFlowRequest $request)
    {
        $params    = $request->validated();
        $startDate = $params['startDate'];
        $endDate   = $params['endDate'];
        $companyId = $this->currentTenantContext->companyId();
        $cashAccountIds = $this->cashAccountIds($companyId);
        $current = $this->generatePeriodReport($companyId, $cashAccountIds, $startDate, $endDate);
        $comparisonPeriod = $this->resolveComparisonPeriod($params);
        $comparison = null;
        $sections = $this->buildSections($current, null);
        $summary = $this->buildSummary($current, null);

        if ($comparisonPeriod !== null) {
            $comparison = $this->generatePeriodReport(
                $companyId,
                $cashAccountIds,
                $comparisonPeriod['startDate'],
                $comparisonPeriod['endDate']
            );
            $sections = $this->buildSections($current, $comparison);
            $summary = $this->buildSummary($current, $comparison);
        }

        return response()->json([
            // Backward-compatible fields used by the current frontend.
            'openingBalance' => $current['openingBalance'],
            'endingBalance'  => $current['endingBalance'],
            'operatingTotal' => $current['operatingTotal'],
            'investingTotal' => $current['investingTotal'],
            'financingTotal' => $current['financingTotal'],
            'netChange'      => $current['netChange'],
            'items'          => $current['items'],
            'unmappedTransactions' => $current['unmappedTransactions'],
            'unmappedAccounts' => $current['unmappedAccounts'],
            'warnings' => $current['warnings'],
            'reconciliationDifference' => $current['reconciliationDifference'],

            // Structured fields for comparison-capable clients.
            'period' => [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ],
            'comparisonPeriod' => $comparisonPeriod,
            'comparison' => $comparison,
            'sections' => $sections,
            'summary' => $summary,
            'basis' => 'direct_cash_account_movement',
        ])->setStatusCode(200);
    }

    /**
     * Generate cash flow amounts for one date range.
     */
    private function generatePeriodReport(string $companyId, array $cashAccountIds, string $startDate, string $endDate): array
    {
        if ($cashAccountIds === []) {
            return [
                'openingBalance' => 0.0,
                'endingBalance' => 0.0,
                'operatingTotal' => 0.0,
                'investingTotal' => 0.0,
                'financingTotal' => 0.0,
                'netChange' => 0.0,
                'items' => collect(),
                'unmappedTransactions' => [],
                'unmappedAccounts' => [],
                'warnings' => ['No active cash or cash equivalent posting accounts were found.'],
                'reconciliationDifference' => 0.0,
            ];
        }

        $openingBalance = $this->computeCashBalance($companyId, $cashAccountIds, '<', $startDate);
        $actualEndingBalance = $this->computeCashBalance($companyId, $cashAccountIds, '<=', $endDate);
        $journalRows = $this->cashMovementJournalRows($companyId, $cashAccountIds, $startDate, $endDate);
        $movement = $this->buildCashMovementLines($journalRows, $cashAccountIds);
        $items = collect($movement['items'])->values();

        $operatingTotal = round((float) $items->where('type', 'operating')->sum('total'), 2);
        $investingTotal = round((float) $items->where('type', 'investing')->sum('total'), 2);
        $financingTotal = round((float) $items->where('type', 'financing')->sum('total'), 2);
        $netChange      = round($operatingTotal + $investingTotal + $financingTotal, 2);
        $expectedEndingBalance = round($openingBalance + $netChange, 2);
        $reconciliationDifference = round($actualEndingBalance - $expectedEndingBalance, 2);
        $warnings = $movement['warnings'];

        if (abs($reconciliationDifference) >= 0.01) {
            $warnings[] = sprintf(
                'Cash Flow Statement is out of balance by %s because some cash movements need classification review.',
                number_format($reconciliationDifference, 2, '.', '')
            );
        }

        return [
            'openingBalance' => round($openingBalance, 2),
            'endingBalance'  => round($actualEndingBalance, 2),
            'operatingTotal' => $operatingTotal,
            'investingTotal' => $investingTotal,
            'financingTotal' => $financingTotal,
            'netChange'      => $netChange,
            'items'          => $items,
            'unmappedTransactions' => $movement['unmappedTransactions'],
            'unmappedAccounts' => $movement['unmappedAccounts'],
            'warnings' => array_values(array_unique($warnings)),
            'reconciliationDifference' => $reconciliationDifference,
        ];
    }

    /**
     * Computes the opening cash balance by summing all cash transactions before the start date
     */
    private function computeCashBalance(string $companyId, array $cashAccountIds, string $operator, string $date): float
    {
        return (float) (JournalItem::whereIn('account_id', $cashAccountIds)
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', $operator, $date)
            ->selectRaw('
            SUM(CASE WHEN journal_items.type = ? THEN journal_items.amount ELSE 0 END) -
            SUM(CASE WHEN journal_items.type = ? THEN journal_items.amount ELSE 0 END) as balance
        ', ['debit', 'credit'])
            ->value('balance') ?? 0);
    }

    private function cashMovementJournalRows(string $companyId, array $cashAccountIds, string $startDate, string $endDate): Collection
    {
        return JournalItem::query()
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->leftJoin('account_types', 'account_types.id', '=', 'accounts.account_type_id')
            ->leftJoin('detail_types', 'detail_types.id', '=', 'accounts.detail_type_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
            ->whereIn('journal_entries.id', function ($query) use ($companyId, $cashAccountIds, $startDate, $endDate) {
                $query->select('cash_items.journal_entry_id')
                    ->from('journal_items as cash_items')
                    ->join('journal_entries as cash_entries', 'cash_items.journal_entry_id', '=', 'cash_entries.id')
                    ->where('cash_entries.company_id', $companyId)
                    ->where('cash_entries.status', 'posted')
                    ->whereBetween('cash_entries.entry_date', [$startDate, $endDate])
                    ->whereIn('cash_items.account_id', $cashAccountIds);
            })
            ->select([
                'journal_entries.id as journal_entry_id',
                'journal_entries.entry_date',
                'journal_entries.prefix',
                'journal_entries.entry_number',
                'journal_entries.reference_number',
                'journal_items.id as journal_item_id',
                'journal_items.account_id',
                'journal_items.type as journal_item_type',
                'journal_items.amount',
                'journal_items.description',
                'accounts.name as account_name',
                'accounts.type as account_type',
                'account_types.code as account_type_code',
                'account_types.name as account_type_name',
                'detail_types.code as detail_type_code',
                'detail_types.name as detail_type_name',
            ])
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.created_at')
            ->orderBy('journal_items.created_at')
            ->get();
    }

    /**
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     unmappedTransactions: array<int, array<string, mixed>>,
     *     unmappedAccounts: array<int, array<string, mixed>>,
     *     warnings: array<int, string>
     * }
     */
    private function buildCashMovementLines(Collection $journalRows, array $cashAccountIds): array
    {
        $itemsByKey = [];
        $unmappedTransactions = [];
        $unmappedAccounts = [];
        $warnings = [];

        foreach ($journalRows->groupBy('journal_entry_id') as $journalEntryId => $rows) {
            $cashChange = round((float) $rows
                ->filter(fn (object $row): bool => in_array((string) $row->account_id, $cashAccountIds, true))
                ->sum(fn (object $row): float => $row->journal_item_type === 'debit'
                    ? (float) $row->amount
                    : -1 * (float) $row->amount
                ), 2);

            if (abs($cashChange) < 0.01) {
                continue;
            }

            $oppositeLineType = $cashChange > 0 ? 'credit' : 'debit';
            $oppositeRows = $rows
                ->reject(fn (object $row): bool => in_array((string) $row->account_id, $cashAccountIds, true))
                ->filter(fn (object $row): bool => $row->journal_item_type === $oppositeLineType && (float) $row->amount > 0)
                ->values();

            if ($oppositeRows->isEmpty()) {
                $unmappedTransactions[] = $this->unmappedTransactionPayload($rows, $cashChange, 'No opposite non-cash line was found for the cash movement.');
                continue;
            }

            $oppositeTotal = round((float) $oppositeRows->sum('amount'), 2);

            if ($oppositeTotal <= 0) {
                $unmappedTransactions[] = $this->unmappedTransactionPayload($rows, $cashChange, 'Opposite non-cash lines have no positive amount.');
                continue;
            }

            $absoluteCashChange = abs($cashChange);
            $remaining = $absoluteCashChange;
            $lastIndex = $oppositeRows->count() - 1;

            foreach ($oppositeRows as $index => $row) {
                $allocatedAbsolute = $index === $lastIndex
                    ? $remaining
                    : round($absoluteCashChange * ((float) $row->amount / $oppositeTotal), 2);
                $remaining = round($remaining - $allocatedAbsolute, 2);
                $signedAmount = round($cashChange > 0 ? $allocatedAbsolute : -1 * $allocatedAbsolute, 2);

                if (abs($signedAmount) < 0.01) {
                    continue;
                }

                $category = $this->determineCashFlowCategory(
                    (string) $row->account_type,
                    $row->account_type_code,
                    $row->account_type_name,
                    $row->detail_type_code,
                    $row->detail_type_name
                );

                if ($category === null) {
                    $unmappedTransactions[] = $this->unmappedTransactionPayload($rows, $signedAmount, 'Opposite account could not be classified.');
                    $unmappedAccounts[(string) $row->account_id] = [
                        'accountId' => (string) $row->account_id,
                        'accountName' => (string) $row->account_name,
                        'accountType' => (string) $row->account_type,
                        'accountTypeCode' => $row->account_type_code,
                        'detailTypeCode' => $row->detail_type_code,
                    ];
                    continue;
                }

                $key = $category.'|'.(string) $row->account_id;
                $itemsByKey[$key] ??= [
                    'accountId' => (string) $row->account_id,
                    'accountName' => (string) $row->account_name,
                    'total' => 0.0,
                    'type' => $category,
                    'transactions' => [],
                ];
                $itemsByKey[$key]['total'] = round((float) $itemsByKey[$key]['total'] + $signedAmount, 2);
                $itemsByKey[$key]['transactions'][] = [
                    'id' => (string) $row->journal_item_id,
                    'journalEntryId' => (string) $row->journal_entry_id,
                    'journalNumber' => (string) $row->prefix.'-'.str_pad((string) $row->entry_number, 4, '0', STR_PAD_LEFT),
                    'date' => (string) $row->entry_date,
                    'description' => (string) ($row->description ?? ''),
                    'amount' => $signedAmount,
                ];
            }
        }

        if ($unmappedTransactions !== []) {
            $warnings[] = 'Some cash movements could not be classified and need review.';
        }

        $items = collect($itemsByKey)
            ->filter(fn (array $item) => abs((float) $item['total']) >= 0.01)
            ->values()
            ->all();

        return [
            'items' => $items,
            'unmappedTransactions' => array_values($unmappedTransactions),
            'unmappedAccounts' => array_values($unmappedAccounts),
            'warnings' => $warnings,
        ];
    }

    private function unmappedTransactionPayload(Collection $rows, float $amount, string $reason): array
    {
        $first = $rows->first();

        return [
            'journalEntryId' => (string) $first->journal_entry_id,
            'journalNumber' => (string) $first->prefix.'-'.str_pad((string) $first->entry_number, 4, '0', STR_PAD_LEFT),
            'entryDate' => (string) $first->entry_date,
            'referenceNumber' => $first->reference_number,
            'amount' => round($amount, 2),
            'reason' => $reason,
            'accounts' => $rows
                ->map(fn (object $row): array => [
                    'accountId' => (string) $row->account_id,
                    'accountName' => (string) $row->account_name,
                    'lineType' => (string) $row->journal_item_type,
                    'amount' => round((float) $row->amount, 2),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Resolve posting cash/bank accounts under the existing cash account groups.
     */
    private function cashAccountIds(string $companyId): array
    {
        $targetGroupNames = ['Cash In Hand', 'Cash on Hand', 'Petty Cash', 'Bank Accounts', 'Cash in Bank', 'Cash and Cash Equivalents'];
        $targetDetailTypeCodes = ['cash-in-hand', 'cash-on-hand', 'petty-cash', 'bank-accounts', 'bank-account', 'cash-and-cash-equivalents'];
        $targetDetailTypeNames = ['Cash In Hand', 'Cash on Hand', 'Petty Cash', 'Bank Accounts', 'Bank Account', 'Cash in Bank', 'Cash and Cash Equivalents'];

        $accounts = Account::query()
            ->from('accounts as a')
            ->leftJoin('detail_types as dt', 'dt.id', '=', 'a.detail_type_id')
            ->where('a.company_id', $companyId)
            ->select(
                'a.id',
                'a.name',
                'a.parent_id',
                'a.type',
                'a.is_posting',
                'a.status',
                'dt.code as detail_type_code',
                'dt.name as detail_type_name'
            )
            ->get();

        $targetIds = $accounts
            ->filter(function (object $account) use ($targetGroupNames, $targetDetailTypeCodes, $targetDetailTypeNames) {
                return in_array((string) $account->name, $targetGroupNames, true)
                    || in_array((string) ($account->detail_type_code ?? ''), $targetDetailTypeCodes, true)
                    || in_array((string) ($account->detail_type_name ?? ''), $targetDetailTypeNames, true);
            })
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($targetIds === []) {
            return [];
        }

        $descendantIds = $this->descendantIds($accounts, $targetIds);

        return $accounts
            ->filter(function (object $account) use ($targetIds, $descendantIds) {
                $id = (string) $account->id;

                return ($account->is_posting === true)
                    && $account->status === 'active'
                    && $account->type === 'asset'
                    && (in_array($id, $targetIds, true) || in_array($id, $descendantIds, true));
            })
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    private function descendantIds(Collection $accounts, array $parentIds): array
    {
        $ids = [];
        $queue = $parentIds;

        while ($queue !== []) {
            $parentId = array_shift($queue);
            $children = $accounts
                ->filter(fn (object $account) => (string) $account->parent_id === (string) $parentId)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            foreach ($children as $childId) {
                if (!in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }

    private function resolveComparisonPeriod(array $params): ?array
    {
        if (!filter_var($params['compareEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $mode = $params['comparisonMode'] ?? 'previous_period';
        $start = Carbon::parse($params['startDate']);
        $end = Carbon::parse($params['endDate']);

        $period = match ($mode) {
            'previous_year' => [
                'startDate' => $start->copy()->subYear()->toDateString(),
                'endDate' => $end->copy()->subYear()->toDateString(),
            ],
            'custom' => [
                'startDate' => (string) $params['comparisonStartDate'],
                'endDate' => (string) $params['comparisonEndDate'],
            ],
            default => $this->previousPeriod($start, $end),
        };

        return [
            ...$period,
            'mode' => $mode,
        ];
    }

    private function previousPeriod(Carbon $start, Carbon $end): array
    {
        if ($start->isStartOfMonth() && $end->isEndOfMonth()) {
            return [
                'startDate' => $start->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'endDate' => $start->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ];
        }

        if ($start->isStartOfYear() && $end->isEndOfYear()) {
            return [
                'startDate' => $start->copy()->subYear()->startOfYear()->toDateString(),
                'endDate' => $start->copy()->subYear()->endOfYear()->toDateString(),
            ];
        }

        $days = $start->diffInDays($end) + 1;

        return [
            'startDate' => $start->copy()->subDays($days)->toDateString(),
            'endDate' => $start->copy()->subDay()->toDateString(),
        ];
    }

    private function buildSections(array $current, ?array $comparison): array
    {
        return collect([
            ['key' => 'operating', 'label' => 'Operating Activities', 'totalKey' => 'operatingTotal'],
            ['key' => 'investing', 'label' => 'Investing Activities', 'totalKey' => 'investingTotal'],
            ['key' => 'financing', 'label' => 'Financing Activities', 'totalKey' => 'financingTotal'],
        ])->map(function (array $section) use ($current, $comparison) {
            $lines = $this->buildSectionLines($section['key'], $current, $comparison);
            $currentTotal = (float) $current[$section['totalKey']];
            $previousTotal = $comparison === null ? null : (float) $comparison[$section['totalKey']];

            return [
                'key' => $section['key'],
                'label' => $section['label'],
                'lines' => $lines,
                'currentTotal' => $currentTotal,
                'previousTotal' => $previousTotal,
                'varianceAmount' => $previousTotal === null ? null : round($currentTotal - $previousTotal, 2),
                'variancePercent' => $previousTotal === null ? null : $this->variancePercent($currentTotal, $previousTotal),
            ];
        })->all();
    }

    private function buildSectionLines(string $type, array $current, ?array $comparison): array
    {
        $currentLines = collect($current['items'])->where('type', $type);
        $comparisonLines = collect($comparison['items'] ?? [])->where('type', $type);

        return $currentLines
            ->concat($comparisonLines)
            ->map(fn (array $item) => (string) ($item['accountId'] ?? $item['accountName']))
            ->unique()
            ->map(function (string $key) use ($currentLines, $comparisonLines) {
                $currentItem = $currentLines->first(fn (array $item) => (string) ($item['accountId'] ?? $item['accountName']) === $key);
                $previousItem = $comparisonLines->first(fn (array $item) => (string) ($item['accountId'] ?? $item['accountName']) === $key);
                $currentAmount = (float) ($currentItem['total'] ?? 0);
                $previousAmount = (float) ($previousItem['total'] ?? 0);

                return [
                    'accountId' => $currentItem['accountId'] ?? $previousItem['accountId'] ?? null,
                    'accountName' => $currentItem['accountName'] ?? $previousItem['accountName'] ?? '',
                    'currentAmount' => $currentAmount,
                    'previousAmount' => $previousAmount,
                    'varianceAmount' => round($currentAmount - $previousAmount, 2),
                    'variancePercent' => $this->variancePercent($currentAmount, $previousAmount),
                    'transactions' => $currentItem['transactions'] ?? [],
                ];
            })
            ->values()
            ->all();
    }

    private function buildSummary(array $current, ?array $comparison): array
    {
        $summary = [
            'openingCashCurrent' => $current['openingBalance'],
            'netCashFlowCurrent' => $current['netChange'],
            'endingCashCurrent' => $current['endingBalance'],
            'openingCashPrevious' => $comparison['openingBalance'] ?? null,
            'netCashFlowPrevious' => $comparison['netChange'] ?? null,
            'endingCashPrevious' => $comparison['endingBalance'] ?? null,
            'reconciliationDifferenceCurrent' => $current['reconciliationDifference'] ?? 0.0,
            'reconciliationDifferencePrevious' => $comparison['reconciliationDifference'] ?? null,
        ];

        $summary['netCashFlowVariance'] = $comparison === null
            ? null
            : round($summary['netCashFlowCurrent'] - $summary['netCashFlowPrevious'], 2);
        $summary['endingCashVariance'] = $comparison === null
            ? null
            : round($summary['endingCashCurrent'] - $summary['endingCashPrevious'], 2);

        return $summary;
    }

    private function variancePercent(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.01) {
            return null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 2);
    }

    /**
     * Determines the cash flow category based on the account taxonomy
     * of the non-cash accounts involved in the journal entry.
     *
     * This system already models business intent through account types and
     * detail types, so cash-flow classification should use those instead of
     * guessing from the five core types alone.
     */
    private function determineCashFlowCategory(
        string $accountType,
        ?string $accountTypeCode = null,
        ?string $accountTypeName = null,
        ?string $detailTypeCode = null,
        ?string $detailTypeName = null
    ): ?string
    {
        $normalizedType = strtolower(trim($accountType));
        $normalizedAccountTypeCode = strtolower(trim((string) $accountTypeCode));
        $normalizedAccountTypeName = strtolower(trim((string) $accountTypeName));
        $normalizedDetailTypeCode = strtolower(trim((string) $detailTypeCode));
        $normalizedDetailTypeName = strtolower(trim((string) $detailTypeName));

        if (in_array($normalizedType, ['revenue', 'expense'], true)) {
            return 'operating';
        }

        if ($normalizedType === 'equity') {
            return 'financing';
        }

        $operatingDetailTypes = [
            'accounts-receivable',
            'stock-assets',
            'tax-assets',
            'loans-and-advances-assets',
            'prepaid-expenses',
            'accounts-payable',
            'accrued-expenses',
            'customer-advances',
            'stock-liabilities',
            'duties-and-taxes',
            'short-term-provisions',
            'long-term-provisions',
            'employee-benefits-obligation',
        ];

        $investingDetailTypes = [
            'capital-equipment',
            'electronic-equipment',
            'furniture-and-fixtures',
            'office-equipment',
            'plants-and-machineries',
            'buildings',
            'software',
            'accumulated-depreciation',
            'cwip-account',
            'investments',
            'securities-and-deposits',
            'short-term-investments',
        ];

        $financingDetailTypes = [
            'loans-liabilities',
            'capital-stock',
            'dividends-paid',
            'opening-balance-equity',
            'retained-earnings',
            'revaluation-surplus',
            'temporary-opening',
        ];

        if (in_array($normalizedDetailTypeCode, $operatingDetailTypes, true)) {
            return 'operating';
        }

        if (in_array($normalizedDetailTypeCode, $investingDetailTypes, true)) {
            return 'investing';
        }

        if (in_array($normalizedDetailTypeCode, $financingDetailTypes, true)) {
            return 'financing';
        }

        if ($normalizedType === 'asset') {
            if (in_array($normalizedAccountTypeCode, ['current-assets', 'temporary-accounts'], true)) {
                return 'operating';
            }

            if (in_array($normalizedAccountTypeCode, ['fixed-assets', 'investments'], true)) {
                return 'investing';
            }

            if (in_array($normalizedAccountTypeName, ['current assets', 'temporary accounts'], true)) {
                return 'operating';
            }

            if (in_array($normalizedAccountTypeName, ['fixed assets', 'investments'], true)) {
                return 'investing';
            }
        }

        if ($normalizedType === 'liability') {
            if (in_array($normalizedAccountTypeCode, ['current-liabilities'], true)) {
                return 'operating';
            }

            if (in_array($normalizedAccountTypeCode, ['non-current-liabilities'], true)) {
                return 'financing';
            }

            if (in_array($normalizedAccountTypeName, ['current liabilities'], true)) {
                return 'operating';
            }

            if (in_array($normalizedAccountTypeName, ['non-current liabilities'], true)) {
                return 'financing';
            }

            return 'financing';
        }

        return null;
    }
}

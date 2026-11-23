<?php

namespace Modules\Accounting\Http\Controllers\Api\GeneralLedger;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Accounting\Http\Requests\GeneralLedger\FetchAccountsRequest;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Services\CurrentTenantContext;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Users\Models\Company;

class GeneralLedgerController extends Controller
{
    public function __construct(
        private readonly CurrentTenantContext $currentTenantContext
    )
    {
    }

    /**
     * Retrieves the total debits, total credits, and
     * transactions of each account within the specified date range
     */
    public function fetchAccounts(FetchAccountsRequest $request)
    {
        $params = $request->validated();
        $companyId = $this->currentTenantContext->companyId();
        $company = Company::query()
            ->select('id', 'default_inventory_asset_account_id')
            ->find($companyId);
        $inventoryOpeningBridge = $this->computeInventoryOpeningBridge(
            $companyId,
            $params['startDate'],
            $company?->default_inventory_asset_account_id
        );

        $accounts = Account::query()
            ->where('accounts.company_id', $companyId)
            ->whereIn('accounts.id', $params['accountIds'])
            ->select('accounts.id', 'accounts.name', 'accounts.code', 'accounts.normal_balance', 'accounts.type')
            ->leftJoin('journal_items', 'accounts.id', '=', 'journal_items.account_id')
            ->leftJoin('journal_entries', function ($join) use ($companyId, $params) {
                $join->on('journal_items.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.company_id', $companyId)
                    ->where('journal_entries.status', 'posted')
                    ->whereBetween('journal_entries.entry_date', [$params['startDate'], $params['endDate']]);
            })
            ->groupBy('accounts.id', 'accounts.name', 'accounts.code', 'accounts.normal_balance', 'accounts.type')
            ->selectRaw('
                SUM(CASE
                    WHEN journal_items.type = ?
                    AND journal_entries.id IS NOT NULL
                    THEN journal_items.amount
                    ELSE 0
                END) as total_debit,
                SUM(CASE
                    WHEN journal_items.type = ?
                    AND journal_entries.id IS NOT NULL
                    THEN journal_items.amount
                    ELSE 0
                END) as total_credit
            ', ['debit', 'credit'])
            ->orderBy('accounts.code')
            ->get()
            ->map(function ($account) use ($companyId, $params, $company, $inventoryOpeningBridge) {
                // Compute the opening balance before the start date and attach the transactions for each account
                $openingBalance = $this->computeOpeningBalance(
                    $companyId,
                    $account->id,
                    $params['startDate'],
                    $account->normal_balance,
                    (string) ($company?->default_inventory_asset_account_id ?? ''),
                    $inventoryOpeningBridge
                );
                $transactions = $this->getTransactions($companyId, $account->id, $params['startDate'], $params['endDate']);

                // Compute the balance based on the account's normal balance
                $debit = (float) ($account->total_debit ?? 0);
                $credit = (float) ($account->total_credit ?? 0);
                $periodNetChange = $account->normal_balance === 'debit'
                    ? $debit - $credit
                    : $credit - $debit;
                $balance = round($openingBalance + $periodNetChange, 2);

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'normalBalance' => $account->normal_balance,
                    'openingBalance' => round($openingBalance, 2),
                    'debit' => round($debit, 2),
                    'credit' => round($credit, 2),
                    'balance' => $balance,
                    'transactions' => $this->attachRunningBalance($transactions, $openingBalance, $account),
                ];
            });

        return response()->json($accounts);
    }

    /**
     * Computes the opening balance of an account by summing all debit and credit transactions before the start date
     */
    private function computeOpeningBalance(
        string $companyId,
        string $accountId,
        string $startDate,
        string $normalBalance,
        string $defaultInventoryAssetAccountId = '',
        float $inventoryOpeningBridge = 0.0
    ): float {
        $balance = JournalItem::query()
            ->where('journal_items.account_id', $accountId)
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '<', $startDate)
            ->selectRaw('
                SUM(CASE WHEN journal_items.type = ? THEN journal_items.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN journal_items.type = ? THEN journal_items.amount ELSE 0 END) as total_credit
            ', ['debit', 'credit'])
            ->first();

        $debit = (float) ($balance?->total_debit ?? 0);
        $credit = (float) ($balance?->total_credit ?? 0);
        $journalOpeningBalance = $normalBalance === 'debit'
            ? $debit - $credit
            : $credit - $debit;

        if ($defaultInventoryAssetAccountId !== '' && $accountId === $defaultInventoryAssetAccountId) {
            return round($journalOpeningBalance + $inventoryOpeningBridge, 2);
        }

        return round($journalOpeningBalance, 2);
    }

    /**
     * Fetches the posted journal entries that reference the account ID sorted by date in ascending order
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
     * Computes the running balance of each transaction
     */
    private function attachRunningBalance(Collection $transactions, float $openingBalance, Account $account): Collection
    {
        $runningBalance = $openingBalance;

        return $transactions->map(function ($transaction) use (&$runningBalance, $account) {
            $debit = $transaction->type === 'debit' ? (float) $transaction->amount : 0.0;
            $credit = $transaction->type === 'credit' ? (float) $transaction->amount : 0.0;

            $runningBalance += $account->normal_balance === 'debit'
                ? $debit - $credit
                : $credit - $debit;

            return [
                'id' => $transaction->id,
                'date' => $transaction->entry_date,
                'journalNumber' => $transaction->prefix . '-' . str_pad($transaction->entry_number, 4, '0', STR_PAD_LEFT),
                'description' => $transaction->description ?? '',
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
            ];
        });
    }

    private function computeInventoryOpeningBridge(
        string $companyId,
        string $startDate,
        ?string $defaultInventoryAssetAccountId
    ): float {
        if (!is_string($defaultInventoryAssetAccountId) || trim($defaultInventoryAssetAccountId) === '') {
            return 0.0;
        }

        // Accounting-only installations have no inventory subledger tables.
        if (!\Illuminate\Support\Facades\Schema::hasTable('inventory_items')) {
            return 0.0;
        }

        $startOfDay = Carbon::parse($startDate)->startOfDay();
        $eligibleItems = InventoryItem::query()
            ->forCompany($companyId)
            ->where('item_type', 'inventory')
            ->where(function ($query) use ($startDate): void {
                $query
                    ->whereDate('created_at', '<', $startDate)
                    ->orWhereDate('stock_as_of', '<', $startDate);
            })
            ->get([
                'id',
                'quantity_on_hand',
                'cost_price',
            ]);

        if ($eligibleItems->isEmpty()) {
            return 0.0;
        }

        $movementsByItem = InventoryMovement::query()
            ->forCompany($companyId)
            ->whereIn('inventory_item_id', $eligibleItems->pluck('id'))
            ->where(function ($query) use ($startDate, $startOfDay): void {
                $query
                    ->whereDate('movement_date', '>=', $startDate)
                    ->orWhere(function ($nestedQuery) use ($startOfDay): void {
                        $nestedQuery
                            ->whereNull('movement_date')
                            ->where('occurred_at', '>=', $startOfDay);
                    });
            })
            ->get([
                'inventory_item_id',
                'direction',
                'quantity',
                'unit_cost',
            ])
            ->groupBy('inventory_item_id');

        $openingValue = 0.0;

        foreach ($eligibleItems as $inventoryItem) {
            $currentUnitCost = $inventoryItem->cost_price === null ? 0.0 : (float) $inventoryItem->cost_price;
            $itemOpeningValue = round((float) $inventoryItem->quantity_on_hand * $currentUnitCost, 2);

            foreach ($movementsByItem->get((string) $inventoryItem->getKey(), collect()) as $movement) {
                $unitCost = $movement->unit_cost === null ? $currentUnitCost : (float) $movement->unit_cost;
                $movementValue = round((float) $movement->quantity * $unitCost, 2);

                if ((string) $movement->direction === 'out') {
                    $itemOpeningValue += $movementValue;
                    continue;
                }

                if ((string) $movement->direction === 'in') {
                    $itemOpeningValue -= $movementValue;
                }
            }

            $openingValue += $itemOpeningValue;
        }

        return round($openingValue, 2);
    }
}

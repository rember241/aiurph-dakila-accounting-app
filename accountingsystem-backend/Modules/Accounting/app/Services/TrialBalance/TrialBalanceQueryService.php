<?php

namespace Modules\Accounting\Services\TrialBalance;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Accounting\Exports\TrialBalanceExport;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Services\CurrentTenantContext;
use Modules\Users\Models\Company;

final readonly class TrialBalanceQueryService
{
    public function __construct(
        private CurrentTenantContext $currentTenantContext
    )
    {}

    /**
     * Returns all posting accounts with their transaction data for the trial balance report
     *
     * @param array $params The validated parameters from GenerateTrialBalanceRequest
     * @return Collection Account transaction data with their debit/credit balances
     */
    public function generateReport(array $params): Collection
    {
        $companyId = $this->currentTenantContext->companyId();
        return $this->getAccountLedgerData($params, $companyId);
    }


    public function downloadReport(array $params)
    {
        $companyId = $this->currentTenantContext->companyId();
        $accounts = $this->getAccountLedgerData($params, $companyId);

        // For the date on the report
        $date = Carbon::parse($params['date'])->format('F d, Y');
        $dateText = "As of $date";

        // Separate Accounts
        $assetAccounts = $accounts->where('type', 'asset');
        $liabilityAccounts = $accounts->where('type', 'liability');
        $equityAccounts = $accounts->where('type', 'equity');
        $revenueAccounts = $accounts->where('type', 'revenue');
        $expenseAccounts = $accounts->where('type', 'expense');

        // Sum account debits/credits (Commented for now; not used)
        // $totalAssetDebit  = $this->sumAccount($assetAccounts, 'debit');
        // $totalAssetCredit = $this->sumAccount($assetAccounts, 'credit');

        // $totalLiabilityDebit  = $this->sumAccount($liabilityAccounts, 'debit');
        // $totalLiabilityCredit = $this->sumAccount($liabilityAccounts, 'credit');

        // $totalEquityDebit  = $this->sumAccount($equityAccounts, 'debit');
        // $totalEquityCredit = $this->sumAccount($equityAccounts, 'credit');

        // $totalRevenueDebit  = $this->sumAccount($revenueAccounts, 'debit');
        // $totalRevenueCredit = $this->sumAccount($revenueAccounts, 'credit');

        // $totalExpenseDebit  = $this->sumAccount($expenseAccounts, 'debit');
        // $totalExpenseCredit = $this->sumAccount($expenseAccounts, 'credit');

        // Debit and credit grand totals
        $totalDebit = $this->sumAccount($accounts, 'debit');
        $totalCredit = $this->sumAccount($accounts, 'credit');

        return match ($params['fileType']) {
            "pdf" => Pdf::loadView('accounting::trial-balance.pdf', [
                'companyName' => Company::find($companyId)->name,
                'dateText' => $dateText,
                'assetAccounts' => $assetAccounts->toArray(),
                'liabilityAccounts' => $liabilityAccounts->toArray(),
                'equityAccounts' => $equityAccounts->toArray(),
                'revenueAccounts' => $revenueAccounts->toArray(),
                'expenseAccounts' => $expenseAccounts->toArray(),
                'totalDebit' => $totalDebit,
                'totalCredit' => $totalCredit,
            ])
                ->setPaper('a4')
                ->setOptions(['enable_php' => true])
                ->download(),

            "csv", "xlsx" => Excel::download(new TrialBalanceExport(
                $params['fileType'],
                Company::find($companyId)->name,
                $dateText,
                $assetAccounts->toArray(),
                $liabilityAccounts->toArray(),
                $equityAccounts->toArray(),
                $revenueAccounts->toArray(),
                $expenseAccounts->toArray(),
                $totalDebit,
                $totalCredit
            ), "trial-balance.{$params['fileType']}")
        };
    }

    private function getAccountLedgerData(array $params, string $companyId): Collection
    {
        return Account::query()
            ->where('accounts.company_id', $companyId)
            ->where('accounts.is_posting', true)
            ->where('accounts.status', 'active')
            ->select('accounts.id', 'accounts.code', 'accounts.name',
                'accounts.type', 'accounts.is_contra', 'accounts.normal_balance')
            ->leftJoin('journal_items', 'journal_items.account_id', '=', 'accounts.id')
            ->leftJoin('journal_entries', function ($join) use ($params, $companyId) {
                $join->on('journal_items.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.company_id', $companyId)
                    ->where('journal_entries.status', 'posted')
                    ->where('journal_entries.entry_date', '<=', $params['date']);
            })
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name',
                'accounts.type', 'accounts.is_contra', 'accounts.normal_balance')
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
            ->orderBy('accounts.name')
            ->get()
            ->map(function ($account) use ($params, $companyId) {
                // Attach the transaction history per account
                $transactions = $this->getTransactions($companyId, $account->id, $params['date']);

                // Compute the balance based on the account's normal balance
                $debit = (float) ($account->total_debit ?? 0);
                $credit = (float) ($account->total_credit ?? 0);
                $balance = $account->normal_balance === 'debit'
                    ? $debit - $credit
                    : $credit - $debit;

                // Assign where the balance is placed if certain conditions are met
                $isNegative = $balance < 0;
                $isDebitAccount = $account->normal_balance === 'debit';
                $isCreditAccount = $account->normal_balance === 'credit';

                $debitValue = 0;
                $creditValue = 0;

                if ($isDebitAccount) {
                    if ($isNegative) $creditValue = abs($balance);
                    else $debitValue = $balance;
                }

                if ($isCreditAccount) {
                    if ($isNegative) $debitValue = abs($balance);
                    else $creditValue = $balance;
                }

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'isContra' => $account->is_contra,
                    'normalBalance' => $account->normal_balance,
                    'debit' => $debitValue,
                    'credit' => $creditValue,
                    'transactions' => $this->attachRunningBalance($transactions, $account),
                ];
            })
            ->filter(fn (array $account) => abs($account['debit']) >= 0.01 || abs($account['credit']) >= 0.01)
            ->values();
    }

    /**
     * Fetches the posted journal entries that reference the account ID sorted by date in ascending order
     */
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
     * Computes the running balance of each transaction
     */
    private function attachRunningBalance(Collection $transactions, Account $account): Collection
    {
        $runningBalance = 0.0;

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

    /**
     * Helper function to sum account debits and credits
     */
    private function sumAccount($accounts, $key)
    {
        return $accounts->reduce(function ($sum, $account) use ($key) {
            return bcadd($sum, (string)($account[$key]), 2);
        }, '0.00');
    }
}

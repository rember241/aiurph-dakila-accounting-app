<?php

namespace Modules\Sales\Services;

use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Models\JournalEntries\JournalNumber;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoicePayment;
use Modules\Users\Models\Company;

/**
 * Creates financial postings for sales invoice payment receipts and their
 * reversals. Revenue is never re-hit on payment receipt — revenue is already
 * recognised when the invoice is issued (see SalesInvoiceAccountingService).
 *
 * Receipt posting:
 *  - Debit  the configured receipt destination (cash/check/bank/online)
 *  - Credit Accounts Receivable
 *
 * Void/reversal posting flips both sides on a new journal entry so history
 * stays intact.
 */
final class SalesInvoicePaymentAccountingService
{
    /**
     * Posts the receipt side of a sales invoice payment.
     *
     * Returns the freshly-created journal entry, already linked to the
     * receipt destination account and the AR account.
     */
    public function postPaymentReceipt(SalesInvoicePayment $payment, SalesInvoice $salesInvoice): JournalEntry
    {
        $company = $this->resolveCompanyForPosting($salesInvoice);
        $receiptAccountId = $this->resolveEffectiveReceiptAccountId(
            $company,
            (string) $payment->payment_method,
            $payment->receipt_account_id
        );

        $description = sprintf(
            'Receipt for sales invoice %s',
            (string) $salesInvoice->invoice_number
        );

        return $this->createJournalEntry(
            company: $company,
            entryDate: (string) $payment->payment_date->toDateString(),
            referenceNumber: $this->buildReferenceNumber($salesInvoice, $payment),
            notes: sprintf(
                'Financial posting for payment on sales invoice %s.',
                (string) $salesInvoice->invoice_number
            ),
            items: [
                $this->debitItem($receiptAccountId, (float) $payment->amount, $description),
                $this->creditItem(
                    (string) $company->default_accounts_receivable_account_id,
                    (float) $payment->amount,
                    sprintf('Clear sales receivable for invoice %s', (string) $salesInvoice->invoice_number)
                ),
            ]
        );
    }

    /**
     * Posts a reversal journal entry for a previously posted payment receipt.
     *
     * Used by the void flow so we do not silently mutate the original
     * journal entry. The amount reversed is the original payment amount so
     * the AR balance returns to its pre-payment state.
     */
    public function postPaymentReversal(SalesInvoicePayment $payment, SalesInvoice $salesInvoice, string $postingDate): JournalEntry
    {
        $company = $this->resolveCompanyForPosting($salesInvoice);
        $receiptAccountId = $this->resolveEffectiveReceiptAccountId(
            $company,
            (string) $payment->payment_method,
            $payment->receipt_account_id
        );

        $description = sprintf(
            'Void receipt for sales invoice %s',
            (string) $salesInvoice->invoice_number
        );

        return $this->createJournalEntry(
            company: $company,
            entryDate: $postingDate,
            referenceNumber: $this->buildReferenceNumber($salesInvoice, $payment, 'VOID'),
            notes: sprintf(
                'Reversal for voided payment on sales invoice %s.',
                (string) $salesInvoice->invoice_number
            ),
            items: [
                $this->debitItem(
                    (string) $company->default_accounts_receivable_account_id,
                    (float) $payment->amount,
                    sprintf('Restore sales receivable for invoice %s', (string) $salesInvoice->invoice_number)
                ),
                $this->creditItem($receiptAccountId, (float) $payment->amount, $description),
            ]
        );
    }

    /**
     * Resolves the receipt destination account id from the configured company
     * defaults based on the payment method. Rejects with a clear validation
     * error when the configuration is missing.
     */
    public function resolveReceiptAccountId(Company $company, string $paymentMethod): string
    {
        $method = trim($paymentMethod);

        return match ($method) {
            'cash' => $this->requireConfiguredAccount(
                $company->default_cash_receipt_account_id,
                'Set a default Cash Receipt account in Company Settings before recording cash payments.'
            ),
            'check' => $this->requireConfiguredAccount(
                $company->default_check_receipt_account_id,
                'Set a default Check Receipt / clearing account in Company Settings before recording check payments.'
            ),
            'bank_transfer' => $this->requireConfiguredAccount(
                $company->default_bank_receipt_account_id,
                'Set a default Bank Receipt account in Company Settings before recording bank transfer payments.'
            ),
            'online' => $this->requireConfiguredAccount(
                $company->default_online_receipt_account_id,
                'Set a default Online / E-Wallet Receipt account in Company Settings before recording online payments.'
            ),
            default => throw ValidationException::withMessages([
                'payment_method' => 'Payment method must be one of cash, check, bank_transfer or online.',
            ]),
        };
    }

    public function resolveEffectiveReceiptAccountId(Company $company, string $paymentMethod, ?string $explicitAccountId): string
    {
        if (is_string($explicitAccountId) && trim($explicitAccountId) !== '') {
            return $this->validateExplicitReceiptAccount($company, trim($explicitAccountId));
        }

        return $this->resolveReceiptAccountId($company, $paymentMethod);
    }

    private function requireConfiguredAccount(?string $accountId, string $message): string
    {
        if (!is_string($accountId) || trim($accountId) === '') {
            throw ValidationException::withMessages([
                'payment_method' => $message,
            ]);
        }

        return trim($accountId);
    }

    private function validateExplicitReceiptAccount(Company $company, string $accountId): string
    {
        $account = Account::query()
            ->whereKey($accountId)
            ->first();

        if (!$account instanceof Account) {
            throw ValidationException::withMessages([
                'receipt_account_id' => 'Selected receipt account does not exist.',
            ]);
        }

        if ((string) $account->company_id !== (string) $company->getKey()) {
            throw ValidationException::withMessages([
                'receipt_account_id' => 'Selected receipt account must belong to the current company.',
            ]);
        }

        if ((string) $account->type !== 'asset') {
            throw ValidationException::withMessages([
                'receipt_account_id' => 'Selected receipt account must be an asset account.',
            ]);
        }

        if (!(bool) $account->is_posting) {
            throw ValidationException::withMessages([
                'receipt_account_id' => 'Selected receipt account must be a posting account.',
            ]);
        }

        if ((string) $account->status !== 'active') {
            throw ValidationException::withMessages([
                'receipt_account_id' => 'Selected receipt account must be active.',
            ]);
        }

        return (string) $account->getKey();
    }

    private function resolveCompanyForPosting(SalesInvoice $salesInvoice): Company
    {
        $company = Company::query()
            ->with([
                'defaultAccountsReceivableAccount:id,company_id,code,name,type,status,is_posting',
                'defaultCashReceiptAccount:id,company_id,code,name,type,status,is_posting',
                'defaultCheckReceiptAccount:id,company_id,code,name,type,status,is_posting',
                'defaultBankReceiptAccount:id,company_id,code,name,type,status,is_posting',
                'defaultOnlineReceiptAccount:id,company_id,code,name,type,status,is_posting',
            ])
            ->find($salesInvoice->company_id);

        if (!$company instanceof Company) {
            throw ValidationException::withMessages([
                'status' => 'Company settings could not be resolved for this invoice.',
            ]);
        }

        $arAccount = $company->default_accounts_receivable_account_id;
        if (!is_string($arAccount) || trim($arAccount) === '') {
            throw ValidationException::withMessages([
                'status' => 'Set a default Accounts Receivable account in Company Settings before recording payments.',
            ]);
        }

        return $company;
    }

    private function buildReferenceNumber(SalesInvoice $salesInvoice, SalesInvoicePayment $payment, string $suffix = 'RCPT'): string
    {
        $shortPaymentId = substr((string) $payment->getKey(), 0, 8);

        return sprintf('%s-%s-%s', (string) $salesInvoice->invoice_number, $suffix, $shortPaymentId);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function createJournalEntry(
        Company $company,
        string $entryDate,
        string $referenceNumber,
        string $notes,
        array $items
    ): JournalEntry {
        $journalNumber = $this->resolveJournalNumber($company);
        $fundSource = $this->resolveFundSource($company);
        $amount = round(collect($items)->where('type', 'debit')->sum('amount'), 2);

        $entry = JournalEntry::query()->create([
            'company_id' => (string) $company->getKey(),
            'org_unit_id' => null,
            'prefix' => (string) $journalNumber->prefix,
            'entry_number' => (int) $journalNumber->starting_number,
            'entry_date' => $entryDate,
            'type' => 'general',
            'fund_source_id' => (string) $fundSource->getKey(),
            'reference_number' => $referenceNumber,
            'notes' => $notes,
            'amount' => $amount,
            'status' => 'posted',
        ]);

        foreach ($items as $item) {
            JournalItem::query()->create([
                'journal_entry_id' => (string) $entry->getKey(),
                'account_id' => (string) $item['account_id'],
                'tax_id' => null,
                'description' => $item['description'] ?? null,
                'type' => (string) $item['type'],
                'amount' => (float) $item['amount'],
            ]);
        }

        $journalNumber->forceFill([
            'starting_number' => (int) $journalNumber->starting_number + 1,
            'is_used' => true,
        ])->save();

        $fundSource->forceFill([
            'is_used' => true,
        ])->save();

        return $entry->load(['journalItems.account']);
    }

    private function resolveJournalNumber(Company $company): JournalNumber
    {
        $journalNumber = JournalNumber::query()
            ->withTrashed()
            ->where('company_id', (string) $company->getKey())
            ->where('prefix', 'SRCPT')
            ->first();

        if (!$journalNumber instanceof JournalNumber) {
            $journalNumber = JournalNumber::query()->create([
                'company_id' => (string) $company->getKey(),
                'name' => 'Sales Receipt Journal',
                'prefix' => 'SRCPT',
                'status' => 'active',
                'reset_interval' => 'daily',
            ]);
        } elseif ($journalNumber->trashed()) {
            $journalNumber->restore();
        }

        if ((string) $journalNumber->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'The Sales Receipt journal number is inactive. Activate it before recording payments.',
            ]);
        }

        return $journalNumber;
    }

    private function resolveFundSource(Company $company): FundSource
    {
        $fundSource = FundSource::query()
            ->withTrashed()
            ->where('company_id', (string) $company->getKey())
            ->where('name', 'Sales Receipts')
            ->first();

        if (!$fundSource instanceof FundSource) {
            $fundSource = FundSource::query()->create([
                'company_id' => (string) $company->getKey(),
                'name' => 'Sales Receipts',
                'status' => 'active',
            ]);
        } elseif ($fundSource->trashed()) {
            $fundSource->restore();
        }

        if ((string) $fundSource->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'The Sales Receipts fund source is inactive. Activate it before recording payments.',
            ]);
        }

        return $fundSource;
    }

    /**
     * @return array<string, mixed>
     */
    private function debitItem(string $accountId, float $amount, string $description): array
    {
        return [
            'account_id' => $accountId,
            'description' => $description,
            'type' => 'debit',
            'amount' => round($amount, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function creditItem(string $accountId, float $amount, string $description): array
    {
        return [
            'account_id' => $accountId,
            'description' => $description,
            'type' => 'credit',
            'amount' => round($amount, 2),
        ];
    }
}

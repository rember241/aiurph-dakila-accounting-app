<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoicePayment;
use Modules\Users\Models\Company;
use Throwable;

/**
 * Writes sales invoice payment records and drives the receipt-side
 * accounting automation. Every successful create/update posts exactly one
 * journal entry (debit receipt account, credit AR) and links it back to the
 * payment row through journal_entry_id. Edits of already-posted payments
 * are rejected — users must void and re-record instead.
 */
final class SalesInvoicePaymentCommandService
{
    private const ALLOWED_PAYMENT_METHODS = ['cash', 'check', 'bank_transfer', 'online'];

    public function __construct(
        private readonly SalesInvoicePeriodPolicyService $periodPolicyService,
        private readonly SalesInvoicePaymentAccountingService $paymentAccountingService
    ) {
    }

    /**
     * Records a payment and posts the matching receipt journal entry.
     *
     * The invoice row is locked while the outstanding balance is checked so two
     * simultaneous payments cannot overpay the same invoice.
     *
     * @param array<string, mixed> $payload
     * @throws Throwable
     */
    public function create(SalesInvoice $salesInvoice, array $payload): SalesInvoicePayment
    {
        $this->ensureInvoiceCanAcceptPayments($salesInvoice);
        $normalized = $this->normalizePayload($payload);
        $this->ensurePaymentDateIsNotBeforeInvoiceDate($salesInvoice, $normalized['payment_date']);
        $this->periodPolicyService->ensurePaymentDateAllowed($salesInvoice, $normalized['payment_date']);

        return DB::transaction(function () use ($salesInvoice, $normalized): SalesInvoicePayment {
            $lockedInvoice = SalesInvoice::query()
                ->withSum('activePayments as amount_paid_total', 'amount')
                ->whereKey($salesInvoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureInvoiceCanAcceptPayments($lockedInvoice);
            $this->ensureOutstandingBalance($lockedInvoice);
            $this->ensurePaymentDoesNotOverpay($lockedInvoice, (float) $normalized['amount']);

            /** @var SalesInvoicePayment $payment */
            $payment = SalesInvoicePayment::query()->create([
                'company_id' => (string) $lockedInvoice->company_id,
                'sales_invoice_id' => (string) $lockedInvoice->getKey(),
                'payment_date' => $normalized['payment_date'],
                'amount' => (float) $normalized['amount'],
                'reference_no' => $normalized['reference_no'],
                'payment_method' => $normalized['payment_method'],
                'payment_channel' => $normalized['payment_channel'],
                'receipt_account_id' => $normalized['receipt_account_id'],
                'notes' => $normalized['notes'],
            ]);

            $this->postReceiptForPayment($payment, $lockedInvoice);

            return $payment->fresh();
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @throws Throwable
     */
    public function update(SalesInvoice $salesInvoice, SalesInvoicePayment $payment, array $payload): SalesInvoicePayment
    {
        $this->ensureInvoiceCanAcceptPayments($salesInvoice);
        $this->ensurePaymentIsMutable($payment);
        $normalized = $this->normalizePayload($payload);
        $this->ensurePaymentDateIsNotBeforeInvoiceDate($salesInvoice, $normalized['payment_date']);
        $this->periodPolicyService->ensurePaymentDateAllowed($salesInvoice, $normalized['payment_date']);

        return DB::transaction(function () use ($salesInvoice, $payment, $normalized): SalesInvoicePayment {
            $lockedInvoice = SalesInvoice::query()
                ->withSum('activePayments as amount_paid_total', 'amount')
                ->whereKey($salesInvoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPayment = SalesInvoicePayment::query()
                ->where('company_id', $lockedInvoice->company_id)
                ->where('sales_invoice_id', $lockedInvoice->getKey())
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureInvoiceCanAcceptPayments($lockedInvoice);
            $this->ensurePaymentIsMutable($lockedPayment);

            $otherPaymentsTotal = (float) SalesInvoicePayment::query()
                ->where('company_id', $lockedInvoice->company_id)
                ->where('sales_invoice_id', $lockedInvoice->getKey())
                ->where('id', '!=', $lockedPayment->getKey())
                ->whereNull('voided_at')
                ->sum('amount');

            $this->ensureTotalDoesNotOverpay($lockedInvoice, $otherPaymentsTotal + (float) $normalized['amount']);

            $lockedPayment->fill([
                'payment_date' => $normalized['payment_date'],
                'amount' => (float) $normalized['amount'],
                'reference_no' => $normalized['reference_no'],
                'payment_method' => $normalized['payment_method'],
                'payment_channel' => $normalized['payment_channel'],
                'receipt_account_id' => $normalized['receipt_account_id'],
                'notes' => $normalized['notes'],
            ]);

            if ($lockedPayment->isDirty()) {
                $lockedPayment->save();
            }

            // If the payment hasn't been posted yet (legacy data or bootstrap
            // path), post it now. Otherwise ensurePaymentIsMutable already
            // bailed out.
            if ($lockedPayment->journal_entry_id === null) {
                $this->postReceiptForPayment($lockedPayment, $lockedInvoice);
            }

            return $lockedPayment->fresh();
        });
    }

    /**
     * @throws Throwable
     */
    public function delete(SalesInvoice $salesInvoice, SalesInvoicePayment $payment): void
    {
        $this->ensureInvoiceCanAcceptPayments($salesInvoice);

        DB::transaction(function () use ($salesInvoice, $payment): void {
            $lockedInvoice = SalesInvoice::query()
                ->whereKey($salesInvoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureInvoiceCanAcceptPayments($lockedInvoice);

            $lockedPayment = SalesInvoicePayment::query()
                ->where('company_id', $lockedInvoice->company_id)
                ->where('sales_invoice_id', $lockedInvoice->getKey())
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedPayment->canDelete()) {
                throw ValidationException::withMessages([
                    'status' => 'This payment has been posted to the general ledger and cannot be deleted. Void it instead to create a reversal entry.',
                ]);
            }

            $lockedPayment->delete();
        });
    }

    /**
     * Voids a posted payment by creating a reversal journal entry. The
     * original receipt entry is left intact for audit purposes — this is
     * the safe alternative to hard deletion.
     *
     * @param array<string, mixed> $payload
     * @throws Throwable
     */
    public function void(SalesInvoice $salesInvoice, SalesInvoicePayment $payment, array $payload = []): SalesInvoicePayment
    {
        $this->ensureInvoiceCanAcceptPayments($salesInvoice);

        $reason = array_key_exists('reason', $payload) && $payload['reason'] !== null
            ? trim((string) $payload['reason'])
            : null;
        $postingDate = array_key_exists('posting_date', $payload) && $payload['posting_date'] !== null
            ? trim((string) $payload['posting_date'])
            : null;

        return DB::transaction(function () use ($salesInvoice, $payment, $reason, $postingDate): SalesInvoicePayment {
            $lockedInvoice = SalesInvoice::query()
                ->whereKey($salesInvoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureInvoiceCanAcceptPayments($lockedInvoice);

            $lockedPayment = SalesInvoicePayment::query()
                ->where('company_id', $lockedInvoice->company_id)
                ->where('sales_invoice_id', $lockedInvoice->getKey())
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedPayment->canVoid()) {
                throw ValidationException::withMessages([
                    'status' => $lockedPayment->isVoided()
                        ? 'This payment has already been voided.'
                        : 'Only posted payments can be voided.',
                ]);
            }

            $effectivePostingDate = $postingDate !== null && $postingDate !== ''
                ? $postingDate
                : now()->toDateString();

            $this->periodPolicyService->ensurePaymentDateAllowed($lockedInvoice, $effectivePostingDate);

            $reversalEntry = $this->paymentAccountingService->postPaymentReversal(
                $lockedPayment,
                $lockedInvoice,
                $effectivePostingDate
            );

            $lockedPayment->forceFill([
                'voided_at' => now(),
                'void_reason' => $reason,
                'reversal_journal_entry_id' => (string) $reversalEntry->getKey(),
            ])->save();

            return $lockedPayment->fresh();
        });
    }

    /**
     * Payments are allowed only after the invoice becomes collectible.
     */
    private function ensureInvoiceCanAcceptPayments(SalesInvoice $salesInvoice): void
    {
        if ((string) $salesInvoice->status === 'issued') {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Payments can only be recorded for issued invoices.',
        ]);
    }

    private function ensureOutstandingBalance(SalesInvoice $salesInvoice): void
    {
        if (round($salesInvoice->balanceDue(), 2) > 0.0) {
            return;
        }

        throw ValidationException::withMessages([
            'amount' => 'This invoice is already fully paid and cannot accept additional payments.',
        ]);
    }

    private function ensurePaymentIsMutable(SalesInvoicePayment $payment): void
    {
        if ($payment->isVoided()) {
            throw ValidationException::withMessages([
                'status' => 'Voided payments cannot be edited.',
            ]);
        }

        if ($payment->journal_entry_id !== null) {
            throw ValidationException::withMessages([
                'status' => 'This payment has already been posted to the general ledger. Void it and record a new payment to make changes.',
            ]);
        }
    }

    /**
     * Posts the receipt side of the payment and writes the journal link back.
     *
     * The linked journal entry is the audit anchor for cash/bank movement, so
     * posted payments are edited through void/re-record instead of mutation.
     */
    private function postReceiptForPayment(SalesInvoicePayment $payment, SalesInvoice $invoice): void
    {
        $journalEntry = $this->paymentAccountingService->postPaymentReceipt($payment, $invoice);

        $company = $this->resolveCompany((string) $invoice->company_id);
        $receiptAccountId = $this->paymentAccountingService->resolveEffectiveReceiptAccountId(
            $company,
            (string) $payment->payment_method,
            $payment->receipt_account_id
        );

        $payment->forceFill([
            'journal_entry_id' => (string) $journalEntry->getKey(),
            'receipt_account_id' => $receiptAccountId,
            'accounting_posted_at' => now(),
        ])->save();
    }

    private function resolveCompany(string $companyId): Company
    {
        $company = Company::query()->find($companyId);

        if (!$company instanceof Company) {
            throw ValidationException::withMessages([
                'status' => 'Company settings could not be resolved for this invoice.',
            ]);
        }

        return $company;
    }

    private function ensurePaymentDateIsNotBeforeInvoiceDate(SalesInvoice $salesInvoice, string $paymentDate): void
    {
        if ($paymentDate >= (string) $salesInvoice->invoice_date->toDateString()) {
            return;
        }

        throw ValidationException::withMessages([
            'payment_date' => 'Payment date cannot be earlier than the invoice date.',
        ]);
    }

    private function ensurePaymentDoesNotOverpay(SalesInvoice $salesInvoice, float $amount): void
    {
        $currentTotal = $salesInvoice->amountPaid();
        $this->ensureTotalDoesNotOverpay($salesInvoice, $currentTotal + $amount);
    }

    private function ensureTotalDoesNotOverpay(SalesInvoice $salesInvoice, float $candidateTotalPaid): void
    {
        if (round($candidateTotalPaid, 2) <= round($salesInvoice->totalAmount(), 2)) {
            return;
        }

        throw ValidationException::withMessages([
            'amount' => 'Payment would exceed the invoice total.',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $paymentMethod = array_key_exists('payment_method', $payload) && $payload['payment_method'] !== null
            ? trim((string) $payload['payment_method'])
            : null;

        if ($paymentMethod === null || $paymentMethod === '' || !in_array($paymentMethod, self::ALLOWED_PAYMENT_METHODS, true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Payment method must be one of cash, check, bank_transfer or online.',
            ]);
        }

        return [
            'payment_date' => trim((string) $payload['payment_date']),
            'amount' => (float) $payload['amount'],
            'receipt_account_id' => array_key_exists('receipt_account_id', $payload) && $payload['receipt_account_id'] !== null
                ? trim((string) $payload['receipt_account_id'])
                : null,
            'reference_no' => array_key_exists('reference_no', $payload) && $payload['reference_no'] !== null
                ? trim((string) $payload['reference_no'])
                : null,
            'payment_method' => $paymentMethod,
            'payment_channel' => array_key_exists('payment_channel', $payload) && $payload['payment_channel'] !== null
                ? (function () use ($payload): ?string {
                    $value = trim((string) $payload['payment_channel']);

                    return $value === '' ? null : $value;
                })()
                : null,
            'notes' => array_key_exists('notes', $payload) && $payload['notes'] !== null
                ? trim((string) $payload['notes'])
                : null,
        ];
    }
}

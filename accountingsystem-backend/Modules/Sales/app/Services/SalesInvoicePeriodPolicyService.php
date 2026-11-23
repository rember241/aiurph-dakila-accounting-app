<?php

namespace Modules\Sales\Services;

use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Sales\Models\SalesInvoice;

/**
 * Enforces accounting-period controls for operational sales events.
 */
/**
 * Centralizes accounting-period checks for sales documents and payments.
 *
 * Sales touches receivables, revenue, inventory, and cash/bank, so keeping the
 * period gate in one service avoids each command service inventing a slightly
 * different rule.
 */
final class SalesInvoicePeriodPolicyService
{
    public function ensureInvoiceIssueAllowed(SalesInvoice $salesInvoice): AccountingPeriod
    {
        $salesInvoice->loadMissing('lines');

        return $this->ensurePostingAllowed(
            (string) $salesInvoice->company_id,
            (string) $salesInvoice->invoice_date->toDateString(),
            $this->requiredSubledgersForInvoice($salesInvoice),
            'invoice_date'
        );
    }

    public function ensureInvoiceCancellationAllowed(SalesInvoice $salesInvoice, string $postingDate): AccountingPeriod
    {
        $salesInvoice->loadMissing('lines');

        return $this->ensurePostingAllowed(
            (string) $salesInvoice->company_id,
            $postingDate,
            $this->requiredSubledgersForInvoice($salesInvoice),
            'status'
        );
    }

    public function ensurePaymentDateAllowed(SalesInvoice $salesInvoice, string $paymentDate): AccountingPeriod
    {
        return $this->ensurePostingAllowed(
            (string) $salesInvoice->company_id,
            $paymentDate,
            ['ar'],
            'payment_date'
        );
    }

    /**
     * @param array<int, string> $subledgers
     */
    public function ensurePostingAllowed(
        string $companyId,
        string $postingDate,
        array $subledgers,
        string $errorField
    ): AccountingPeriod {
        $period = AccountingPeriod::query()
            ->forCompany($companyId)
            ->whereDate('start_date', '<=', $postingDate)
            ->whereDate('end_date', '>=', $postingDate)
            ->first();

        if (!$period instanceof AccountingPeriod) {
            throw ValidationException::withMessages([
                $errorField => 'Posting is not allowed because this date is outside a configured accounting period.',
            ]);
        }

        foreach ($subledgers as $subledger) {
            if ($period->canAcceptPosting($subledger)) {
                continue;
            }

            throw ValidationException::withMessages([
                $errorField => $this->blockedMessageForSubledger($subledger),
            ]);
        }

        return $period;
    }

    /**
     * @return array<int, string>
     */
    private function requiredSubledgersForInvoice(SalesInvoice $salesInvoice): array
    {
        $subledgers = ['ar'];

        $hasInventoryLines = $salesInvoice->lines->contains(
            static fn ($line): bool => (string) $line->item_type_snapshot === 'inventory'
        );

        if ($hasInventoryLines) {
            $subledgers[] = 'inventory';
        }

        return $subledgers;
    }

    private function blockedMessageForSubledger(string $subledger): string
    {
        return match ($subledger) {
            'inventory' => 'Issuing is not allowed because inventory is locked for this accounting period.',
            'ar' => 'Issuing is not allowed because accounts receivable is locked for this accounting period.',
            default => 'Posting is not allowed in this accounting period.',
        };
    }
}

<?php

namespace Modules\Customers\Services;

use Carbon\Carbon;
use Modules\Customers\Models\Customer;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoicePayment;
use Modules\Users\Models\Company;

/**
 * Generates a date-ranged account statement for a single customer.
 *
 * Statement anatomy
 * ─────────────────
 *  Opening balance  Amounts outstanding on issued invoices whose invoice_date
 *                   precedes the period start, net of any payments already
 *                   applied to those invoices before the period start.
 *
 *  Period debits    Each issued invoice whose invoice_date falls within
 *                   [date_from, date_to], shown chronologically.
 *
 *  Period credits   Every payment — whether applied to a pre-period or an
 *                   in-period invoice — whose payment_date falls within
 *                   [date_from, date_to], shown chronologically.
 *
 *  Closing balance  opening_balance + period_invoiced − period_paid.
 *
 * Drafts and cancelled invoices are excluded from all calculations.
 */
/**
 * Builds customer-facing account statements from invoices and receipts.
 *
 * Statement balances are calculated from the same invoice/payment records used
 * by Sales, so printed billing statements stay aligned with the customer ledger.
 */
final class CustomerAccountStatementService
{
    /**
     * @return array<string, mixed>
     */
    public function generate(
        string   $companyId,
        Company  $company,
        Customer $customer,
        Carbon   $dateFrom,
        Carbon   $dateTo
    ): array {
        // ── 1. Fetch all issued invoices for this customer with payments ──────────
        /** @var \Illuminate\Database\Eloquent\Collection<int, SalesInvoice> $allIssued */
        $allIssued = SalesInvoice::query()
            ->forCompany($companyId)
            ->where('customer_id', $customer->id)
            ->where('status', 'issued')
            ->with([
                'payments' => static fn ($q) => $q
                    ->orderBy('payment_date')
                    ->orderBy('created_at'),
            ])
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get();

        // ── 2. Partition into pre-period vs. in-period invoices ──────────────────
        $prePeriod = $allIssued->filter(
            fn (SalesInvoice $i) => $i->invoice_date->lt($dateFrom)
        );

        $inPeriod = $allIssued->filter(
            fn (SalesInvoice $i) => !$i->invoice_date->lt($dateFrom)
                && !$i->invoice_date->gt($dateTo)
        );

        // ── 3. Opening balance ───────────────────────────────────────────────────
        //       For every pre-period invoice: total_amount minus all payments
        //       that were applied before the period start, floored at zero.
        $openingBalance = 0.0;

        foreach ($prePeriod as $invoice) {
            $paidBefore = (float) $invoice->payments
                ->filter(fn (SalesInvoicePayment $p) => $p->payment_date->lt($dateFrom))
                ->sum('amount');

            $openingBalance += max(0.0, round($invoice->totalAmount() - $paidBefore, 2));
        }

        $openingBalance = round($openingBalance, 2);

        // ── 4. Collect raw entries for the period ─────────────────────────────────
        //       In-period invoices become debit entries.
        //       Payments (on any customer invoice) within the period become credits.
        //       The sort key ensures stable, deterministic ordering: invoices before
        //       payments on the same date, then by invoice number / reference.
        $rawEntries = [];

        foreach ($inPeriod as $invoice) {
            $rawEntries[] = [
                'sort_key' => '0|' . $invoice->invoice_date->toDateString() . '|' . $invoice->invoice_number,
                'type'     => 'invoice',
                'invoice'  => $invoice,
                'payment'  => null,
            ];
        }

        foreach ($allIssued as $invoice) {
            foreach ($invoice->payments as $payment) {
                if ($payment->payment_date->lt($dateFrom) || $payment->payment_date->gt($dateTo)) {
                    continue;
                }

                $rawEntries[] = [
                    'sort_key' => '1|' . $payment->payment_date->toDateString() . '|' . ($payment->reference_no ?? $payment->id),
                    'type'     => 'payment',
                    'invoice'  => $invoice,
                    'payment'  => $payment,
                ];
            }
        }

        usort(
            $rawEntries,
            static fn (array $a, array $b): int => strcmp($a['sort_key'], $b['sort_key'])
        );

        // ── 5. Build transaction rows with a running balance ──────────────────────
        $transactions   = [];
        $runningBalance = $openingBalance;
        $totalInvoiced  = 0.0;
        $totalPaid      = 0.0;

        foreach ($rawEntries as $entry) {
            if ($entry['type'] === 'invoice') {
                /** @var SalesInvoice $inv */
                $inv    = $entry['invoice'];
                $amount = $inv->totalAmount();

                $runningBalance = round($runningBalance + $amount, 2);
                $totalInvoiced  = round($totalInvoiced + $amount, 2);

                $transactions[] = [
                    'type'        => 'invoice',
                    'date'        => $inv->invoice_date->toDateString(),
                    'reference'   => $inv->invoice_number,
                    'description' => 'Invoice #' . $inv->invoice_number,
                    'debit'       => $amount,
                    'credit'      => 0.0,
                    'balance'     => $runningBalance,
                    'invoice'     => [
                        'id'             => (string) $inv->getKey(),
                        'invoice_number' => $inv->invoice_number,
                        'invoice_date'   => $inv->invoice_date->toDateString(),
                        'due_date'       => $inv->due_date?->toDateString(),
                        'status'         => $inv->status,
                        'display_status' => $inv->displayStatus(),
                        'subtotal'       => round((float) $inv->subtotal, 2),
                        'tax_amount'     => $inv->taxAmount(),
                        'total_amount'   => $inv->totalAmount(),
                        'amount_paid'    => $inv->amountPaid(),
                        'balance_due'    => $inv->balanceDue(),
                        'notes'          => $inv->notes,
                    ],
                ];
            } else {
                /** @var SalesInvoicePayment $pmt */
                $pmt    = $entry['payment'];
                /** @var SalesInvoice $inv */
                $inv    = $entry['invoice'];
                $amount = round((float) $pmt->amount, 2);

                $runningBalance = round($runningBalance - $amount, 2);
                $totalPaid      = round($totalPaid + $amount, 2);

                $transactions[] = [
                    'type'        => 'payment',
                    'date'        => $pmt->payment_date->toDateString(),
                    'reference'   => $pmt->reference_no,
                    'description' => 'Payment on Invoice #' . $inv->invoice_number,
                    'debit'       => 0.0,
                    'credit'      => $amount,
                    'balance'     => $runningBalance,
                    'payment'     => [
                        'id'             => (string) $pmt->getKey(),
                        'invoice_id'     => (string) $inv->getKey(),
                        'invoice_number' => $inv->invoice_number,
                        'payment_date'   => $pmt->payment_date->toDateString(),
                        'reference_no'   => $pmt->reference_no,
                        'payment_method' => $pmt->payment_method,
                        'amount'         => $amount,
                        'notes'          => $pmt->notes,
                    ],
                ];
            }
        }

        $closingBalance = round($openingBalance + $totalInvoiced - $totalPaid, 2);

        // ── 6. Assemble response payload ──────────────────────────────────────────
        return [
            'data' => [
                'customer' => [
                    'id'      => (string) $customer->getKey(),
                    'code'    => $customer->code,
                    'name'    => $customer->name,
                    'email'   => $customer->email,
                    'phone'   => $customer->phone,
                    'address' => $customer->address,
                    'status'  => $customer->status,
                ],
                'company' => [
                    'id'           => (string) $company->getKey(),
                    'name'         => $company->name,
                    'display_name' => $company->display_name,
                    'email'        => $company->email,
                    'phone'        => $company->phone,
                    'address'      => $company->address,
                    'timezone'     => $company->timezone,
                ],
                'period' => [
                    'date_from' => $dateFrom->toDateString(),
                    'date_to'   => $dateTo->toDateString(),
                ],
                'summary' => [
                    'opening_balance' => $openingBalance,
                    'total_invoiced'  => $totalInvoiced,
                    'total_paid'      => $totalPaid,
                    'closing_balance' => $closingBalance,
                ],
                'transactions' => $transactions,
            ],
        ];
    }
}

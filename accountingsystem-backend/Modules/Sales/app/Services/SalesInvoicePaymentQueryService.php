<?php

namespace Modules\Sales\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoicePayment;

final class SalesInvoicePaymentQueryService
{
    public function findInvoiceOrFail(string $companyId, SalesInvoice|string $salesInvoice): SalesInvoice
    {
        $salesInvoiceId = $salesInvoice instanceof SalesInvoice
            ? (string) $salesInvoice->getKey()
            : (string) $salesInvoice;

        $invoice = SalesInvoice::query()
            ->forCompany($companyId)
            ->whereKey($salesInvoiceId)
            ->first();

        if (!$invoice instanceof SalesInvoice) {
            throw (new ModelNotFoundException())->setModel(SalesInvoice::class, [$salesInvoiceId]);
        }

        return $invoice;
    }

    /**
     * Returns the full history of payments for an invoice, including voided
     * receipts so the audit trail stays intact.
     *
     * @return Collection<int, SalesInvoicePayment>
     */
    public function list(SalesInvoice $salesInvoice): Collection
    {
        return $salesInvoice->payments()
            ->with([
                'salesInvoice:id,company_id,customer_id,invoice_number,status,invoice_date,total_amount',
                'salesInvoice.customer:id,company_id,code,name',
            ])
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findPaymentOrFail(SalesInvoice $salesInvoice, SalesInvoicePayment|string $payment): SalesInvoicePayment
    {
        $paymentId = $payment instanceof SalesInvoicePayment
            ? (string) $payment->getKey()
            : (string) $payment;

        $resolved = SalesInvoicePayment::query()
            ->where('company_id', $salesInvoice->company_id)
            ->where('sales_invoice_id', $salesInvoice->getKey())
            ->whereKey($paymentId)
            ->with([
                'salesInvoice:id,company_id,customer_id,invoice_number,status,invoice_date,total_amount',
                'salesInvoice.customer:id,company_id,code,name',
            ])
            ->first();

        if (!$resolved instanceof SalesInvoicePayment) {
            throw (new ModelNotFoundException())->setModel(SalesInvoicePayment::class, [$paymentId]);
        }

        return $resolved;
    }

    /**
     * Company-scoped paginated listing of payments for the standalone Payments
     * tab. Supports optional filters by customer / invoice / payment method /
     * date range / posting status, plus a free-text search on reference
     * number, notes and related invoice/customer identifiers.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(string $companyId, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['perPage'] ?? 15);
        $perPage = max(1, min($perPage, 200));

        return $this->baseListingQuery($companyId, $filters)
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function baseListingQuery(string $companyId, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $customerId = trim((string) ($filters['customer_id'] ?? ''));
        $invoiceId = trim((string) ($filters['sales_invoice_id'] ?? ''));
        $paymentMethod = trim((string) ($filters['payment_method'] ?? ''));
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        return SalesInvoicePayment::query()
            ->forCompany($companyId)
            ->with([
                'salesInvoice:id,company_id,customer_id,invoice_number,status,invoice_date,total_amount',
                'salesInvoice.customer:id,company_id,code,name',
            ])
            ->when($invoiceId !== '', fn (Builder $query) => $query->where('sales_invoice_id', $invoiceId))
            ->when($paymentMethod !== '', fn (Builder $query) => $query->where('payment_method', $paymentMethod))
            ->when($dateFrom !== '', fn (Builder $query) => $query->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query->whereDate('payment_date', '<=', $dateTo))
            ->when($customerId !== '', function (Builder $query) use ($customerId): void {
                $query->whereHas('salesInvoice', function (Builder $invoiceQuery) use ($customerId): void {
                    $invoiceQuery->where('customer_id', $customerId);
                });
            })
            ->when($status !== '', function (Builder $query) use ($status): void {
                match ($status) {
                    'voided' => $query->whereNotNull('voided_at'),
                    'posted' => $query->whereNull('voided_at')->whereNotNull('journal_entry_id'),
                    'unposted' => $query->whereNull('voided_at')->whereNull('journal_entry_id'),
                    default => null,
                };
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $needle = mb_strtolower($search);

                $query->where(function (Builder $nested) use ($needle): void {
                    $nested
                        ->whereRaw('LOWER(COALESCE(reference_no, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(notes, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereHas('salesInvoice', function (Builder $invoiceQuery) use ($needle): void {
                            $invoiceQuery->whereRaw('LOWER(invoice_number) LIKE ?', ["%{$needle}%"]);
                        })
                        ->orWhereHas('salesInvoice.customer', function (Builder $customerQuery) use ($needle): void {
                            $customerQuery
                                ->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                                ->orWhereRaw('LOWER(code) LIKE ?', ["%{$needle}%"]);
                        });
                });
            });
    }
}

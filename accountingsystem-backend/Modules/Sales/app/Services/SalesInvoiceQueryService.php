<?php

namespace Modules\Sales\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Sales\Models\SalesInvoice;

/**
 * Handles company-scoped sales invoice read queries.
 */
final class SalesInvoiceQueryService
{
    private function baseListingQuery(string $companyId, array $filters): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $customerId = trim((string) ($filters['customer_id'] ?? ''));

        return SalesInvoice::query()
            ->forCompany($companyId)
            ->with([
                'customer:id,company_id,code,name,email,phone,address,status',
                'lines',
            ])
            ->withCount('lines')
            ->withSum('activePayments as amount_paid_total', 'amount')
            ->when($q !== '', function (Builder $query) use ($q): void {
                $needle = mb_strtolower($q);

                $query->where(function (Builder $nestedQuery) use ($needle): void {
                    $nestedQuery
                        ->whereRaw('LOWER(invoice_number) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(notes, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($needle): void {
                            $customerQuery
                                ->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                                ->orWhereRaw('LOWER(code) LIKE ?', ["%{$needle}%"]);
                        })
                        ->orWhereHas('lines', function (Builder $lineQuery) use ($needle): void {
                            $lineQuery
                                ->whereRaw('LOWER(item_name_snapshot) LIKE ?', ["%{$needle}%"])
                                ->orWhereRaw('LOWER(item_code_snapshot) LIKE ?', ["%{$needle}%"]);
                        });
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($customerId !== '', fn (Builder $query) => $query->where('customer_id', $customerId));
    }

    public function paginate(string $companyId, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['perPage'] ?? 10);
        $perPage = max(5, min($perPage, 100));

        $sortField = (string) ($filters['sortField'] ?? 'invoice_date');
        $sortDirParam = strtolower((string) ($filters['sortDir'] ?? 'desc'));
        $sortDirection = $sortDirParam === 'asc' ? 'asc' : 'desc';

        $sortMap = [
            'invoice_number' => 'invoice_number',
            'status' => 'status',
            'invoice_date' => 'invoice_date',
            'due_date' => 'due_date',
            'subtotal' => 'subtotal',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $sortColumn = $sortMap[$sortField] ?? 'invoice_date';

        return $this->baseListingQuery($companyId, $filters)
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('invoice_number', 'asc')
            ->paginate($perPage);
    }

    /**
     * @return array<string, int|float>
     */
    public function stats(string $companyId, array $filters): array
    {
        $query = $this->baseListingQuery($companyId, $filters);

        return [
            'total' => (int) (clone $query)->count(),
            'draft' => (int) (clone $query)->where('status', 'draft')->count(),
            'issued' => (int) (clone $query)->where('status', 'issued')->count(),
            'cancelled' => (int) (clone $query)->where('status', 'cancelled')->count(),
            'subtotal_total' => (float) ((clone $query)->sum('subtotal') ?: 0),
            'subtotal_amount_total' => (float) ((clone $query)->sum('subtotal') ?: 0),
            'line_count_total' => (int) ((clone $query)->get()->sum(fn (SalesInvoice $salesInvoice) => $salesInvoice->lines->count())),
        ];
    }

    /**
     * Resolve a company-scoped sales invoice or fail with a company-safe 404.
     */
    public function findVisibleOrFail(string $companyId, SalesInvoice|string $salesInvoice): SalesInvoice
    {
        $salesInvoiceId = $salesInvoice instanceof SalesInvoice
            ? (string) $salesInvoice->getKey()
            : (string) $salesInvoice;

        $resolved = SalesInvoice::query()
            ->forCompany($companyId)
            ->with([
                'company:id,name,display_name,slug,email,phone,address,timezone,invoice_header_title,invoice_footer_note,invoice_payment_note',
                'customer:id,company_id,code,name,email,phone,address,status',
                'lines',
            ])
            ->withCount('lines')
            ->withSum('activePayments as amount_paid_total', 'amount')
            ->whereKey($salesInvoiceId)
            ->first();

        if (!$resolved instanceof SalesInvoice) {
            throw (new ModelNotFoundException())->setModel(SalesInvoice::class, [$salesInvoiceId]);
        }

        return $resolved;
    }
}

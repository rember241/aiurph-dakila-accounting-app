<?php

namespace Modules\Customers\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Customers\Models\Customer;

/**
 * Handles company-scoped customer read queries.
 */
final class CustomerQueryService
{
    private function baseListingQuery(string $companyId, array $filters): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        return Customer::query()
            ->forCompany($companyId)
            ->when($q !== '', function (Builder $query) use ($q): void {
                $needle = mb_strtolower($q);

                $query->where(function (Builder $nestedQuery) use ($needle): void {
                    $nestedQuery
                        ->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(code) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(customer_group, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(address, \'\')) LIKE ?', ["%{$needle}%"]);
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status));
    }

    public function paginate(string $companyId, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['perPage'] ?? 10);
        $perPage = max(5, min($perPage, 100));

        $sortField = (string) ($filters['sortField'] ?? 'name');
        $sortDirParam = strtolower((string) ($filters['sortDir'] ?? 'asc'));
        $sortDirection = $sortDirParam === 'desc' ? 'desc' : 'asc';

        $sortMap = [
            'code' => 'code',
            'name' => 'name',
            'email' => 'email',
            'phone' => 'phone',
            'status' => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $sortColumn = $sortMap[$sortField] ?? 'name';

        return $this->baseListingQuery($companyId, $filters)
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('name', 'asc')
            ->paginate($perPage);
    }

    /**
     * @return array<string, int>
     */
    public function stats(string $companyId, array $filters): array
    {
        $query = $this->baseListingQuery($companyId, $filters);

        return [
            'total' => (int) (clone $query)->count(),
            'active' => (int) (clone $query)->where('status', 'active')->count(),
            'inactive' => (int) (clone $query)->where('status', 'inactive')->count(),
        ];
    }

    /**
     * Resolve a company-scoped customer or fail with a company-safe 404.
     */
    public function findVisibleOrFail(string $companyId, Customer|string $customer): Customer
    {
        $customerId = $customer instanceof Customer
            ? (string) $customer->getKey()
            : (string) $customer;

        $resolved = Customer::query()
            ->forCompany($companyId)
            ->whereKey($customerId)
            ->first();

        if (!$resolved instanceof Customer) {
            throw (new ModelNotFoundException())->setModel(Customer::class, [$customerId]);
        }

        return $resolved;
    }
}

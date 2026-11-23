<?php

namespace Modules\Accounting\Services\PeriodManagement\Queries;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Services\CurrentTenantContext;


/**
 * Class PaginateAccountingPeriodsQuery
 *
 * Paginate the accounting periods based on the given filters.
 *
 * @package Modules\Accounting\Services\PeriodManagement\Queries
 */
final class PaginateAccountingPeriodsQuery
{
    public function __construct(
        private readonly CurrentTenantContext $currentTenantContext
    )
    {
    }

    public function run(array $filters): LengthAwarePaginator
    {
        $pageSize = (int)($filters['pageSize'] ?? 10);
        $pageSize = max(1, min($pageSize, 100));
        $search = trim((string)($filters['search'] ?? ''));

        $query = AccountingPeriod::query()
            ->forTenant($this->currentTenantContext->id())
            ->with('fiscalYear')
            ->orderBy('period_number', 'asc');

        if (!empty($filters['fiscal_year_id'])) {
            $query->where('fiscal_year_id', $filters['fiscal_year_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('start_date', 'like', "%{$search}%")
                    ->orWhere('end_date', 'like', "%{$search}%")
                    ->orWhereHas('fiscalYear', function ($fiscalYearQuery) use ($search) {
                        $fiscalYearQuery
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });

                if (is_numeric($search)) {
                    $innerQuery->orWhere('period_number', (int)$search);
                }
            });
        }

        return $query->paginate($pageSize);
    }
}

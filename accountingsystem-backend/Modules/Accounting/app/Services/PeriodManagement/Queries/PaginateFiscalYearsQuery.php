<?php

namespace Modules\Accounting\Services\PeriodManagement\Queries;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Services\CurrentTenantContext;


/**
 * Class PaginateFiscalYearsQuery
 *
 * Paginate the fiscal years based on the given filters.
 *
 * @package Modules\Accounting\Services\PeriodManagement\Queries
 */
final class PaginateFiscalYearsQuery
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

        $query = FiscalYear::query()
            ->forTenant($this->currentTenantContext->id())
            ->withCount([
                'periods',
                'periods as closed_periods_count' => fn($periodQuery) => $periodQuery->where('status', 'closed'),
            ])
            ->orderByDesc('start_date');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists('is_current', $filters)) {
            $isCurrent = filter_var($filters['is_current'],
                FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isCurrent !== null) {
                $query->where('is_current', $isCurrent);
            }
        }

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('start_date', 'like', "%{$search}%")
                    ->orWhere('end_date', 'like', "%{$search}%");
            });
        }

        return $query->paginate($pageSize);
    }
}

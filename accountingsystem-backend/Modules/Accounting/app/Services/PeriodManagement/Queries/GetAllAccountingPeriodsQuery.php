<?php

namespace Modules\Accounting\Services\PeriodManagement\Queries;

use Illuminate\Support\Collection;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Services\CurrentTenantContext;

final class GetAllAccountingPeriodsQuery
{
    public function __construct(
        private readonly CurrentTenantContext $currentTenantContext
    ) {}

    public function run(array $filters): Collection
    {
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

        return $query->get();
    }
}

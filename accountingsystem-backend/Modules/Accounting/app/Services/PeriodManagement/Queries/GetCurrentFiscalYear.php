<?php

namespace Modules\Accounting\Services\PeriodManagement\Queries;

use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Services\CurrentTenantContext;

final class GetCurrentFiscalYear
{
    public function __construct(
        private readonly CurrentTenantContext $currentTenantContext
    )
    {
    }

    public function run(): ?FiscalYear
    {
        return FiscalYear::query()
            ->forTenant($this->currentTenantContext->id())
            ->where('is_current', true)
            ->first();
    }
}

<?php

namespace Modules\Accounting\Services\PeriodManagement\Queries;

use Carbon\Carbon;
use Modules\Accounting\Enums\PeriodManagement\PeriodStatus;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Services\CurrentTenantContext;

final class GetPeriodManagementKpi
{
    public function __construct(
        private readonly CurrentTenantContext $currentTenantContext
    )
    {
    }

    public function run(): array
    {
        $tenantId = $this->currentTenantContext->id();

        $currentFiscalYear = FiscalYear::query()
            ->forTenant($tenantId)
            ->where('is_current', true)
            ->first();

        if ($currentFiscalYear === null) {
            return [
                'current_fiscal_year' => null,
                'days_left_in_current_year' => 0,
                'closing_progress' => [
                    'closed_periods' => 0,
                    'total_periods' => 0,
                    'percentage' => 0.0,
                ],
            ];
        }

        $daysLeft = max(
            0,
            Carbon::today()->diffInDays($currentFiscalYear->end_date, false)
        );

        $totalPeriods = AccountingPeriod::query()
            ->forTenant($tenantId)
            ->where('fiscal_year_id', $currentFiscalYear->id)
            ->count();

        $closedPeriods = AccountingPeriod::query()
            ->forTenant($tenantId)
            ->where('fiscal_year_id', $currentFiscalYear->id)
            ->whereIn('status', [
                PeriodStatus::SOFT_CLOSED->value,
                PeriodStatus::CLOSED->value,
            ])
            ->count();

        $percentage = $totalPeriods > 0
            ? round(($closedPeriods / $totalPeriods) * 100, 2)
            : 0.0;

        return [
            'current_fiscal_year' => $currentFiscalYear,
            'days_left_in_current_year' => (int)$daysLeft,
            'closing_progress' => [
                'closed_periods' => $closedPeriods,
                'total_periods' => $totalPeriods,
                'percentage' => $percentage,
            ],
        ];
    }
}

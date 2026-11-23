<?php

namespace Modules\Accounting\Services\PeriodManagement;

use Carbon\Carbon;
use Modules\Accounting\Enums\PeriodManagement\PeriodStatus;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;

final class AccountingPeriodFactory
{
    public function generateTwelveMonthlyPeriods(FiscalYear $fiscalYear, Carbon $start): void
    {
        $cursor = $start->copy()->startOfMonth();

        for ($periodNumber = 1; $periodNumber <= 12; $periodNumber++) {
            AccountingPeriod::create([
                'company_id' => $fiscalYear->company_id ?? $fiscalYear->tenant_id,
                'tenant_id' => $fiscalYear->tenant_id,
                'fiscal_year_id' => $fiscalYear->id,
                'period_number' => $periodNumber,
                'name' => $cursor->format('M Y'),
                'start_date' => $cursor->toDateString(),
                'end_date' => $cursor->endOfMonth()->toDateString(),
                'status' => PeriodStatus::OPEN->value,
            ]);

            $cursor->addMonthNoOverflow()->startOfMonth();
        }

    }
}

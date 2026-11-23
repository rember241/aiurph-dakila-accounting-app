<?php

namespace Modules\Accounting\Rules\PeriodManagement;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Services\CurrentTenantContext;


final readonly class FiscalYearCreateRules
{
    public function __construct(
        private CurrentTenantContext $currentTenantContext
    )
    {
    }

    /**
     * Validate the start and end dates of a fiscal year.
     *
     * @param Carbon $start The start date of the fiscal year.
     * @param Carbon $end The end date of the fiscal year.
     * @return void
     * @throws ValidationException If the start or end date is not in the first or last day of the month, or if the date range does not contain exactly 12 months.
     */
    public function validateDateRange(Carbon $start, Carbon $end): void
    {
        // Check if the start date is on the first day of the month
        if ($start->day !== 1) {
            throw ValidationException::withMessages([
                'start_date' => ['Fiscal year start date must be on the first day of the month'],
            ]);
        }

        // Check if the end date is on the last day of the month
        if ($end->day !== $end->daysInMonth) {
            throw ValidationException::withMessages([
                'end_date' => ['Fiscal year end date must be on the last day of the month'],
            ]);
        }

        /**
         * Calculate the number of months between the start and end dates.
         *
         * @return int The number of months.
         */
        $monthCount = (($end->year - $start->year) * 12) + ($end->month - $start->month) + 1;

        if ($monthCount !== 12) {
            throw ValidationException::withMessages([
                'date_range' => ['Fiscal year must contain exactly 12 monthly periods'],
            ]);
        }

    }

    /**
     * Ensure that the given date range does not overlap with any existing fiscal years.
     *
     * @param Carbon $start The start date of the fiscal year.
     * @param Carbon $end The end date of the fiscal year.
     * @param string|null $ignoreId The ID of the fiscal year to ignore (optional).
     * @return void
     * @throws ValidationException If the date range overlaps with an existing fiscal year
     *                              (excluding the one specified by $ignoreId, if provided).
     */
    public function ensureNoOverlap(Carbon $start, Carbon $end, ?string $ignoreId = null): void
    {
        $tenantId = $this->currentTenantContext->id();

        $query = FiscalYear::query()
            ->forTenant($tenantId)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString());

        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'date_range' => ['Fiscal year overlaps an existing fiscal year.'],
            ]);
        }
    }
}

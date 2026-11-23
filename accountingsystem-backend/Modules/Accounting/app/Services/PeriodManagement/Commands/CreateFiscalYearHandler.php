<?php

namespace Modules\Accounting\Services\PeriodManagement\Commands;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\DTO\PeriodManagement\CreateFiscalYearData;
use Modules\Accounting\Enums\PeriodManagement\FiscalYearStatus;
use Modules\Accounting\Rules\PeriodManagement\FiscalYearCreateRules;
use Modules\Accounting\Services\PeriodManagement\AccountingPeriodFactory;
use Modules\Accounting\Repositories\PeriodManagement\FiscalYearRepository;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Throwable;


/**
 * Handles the creation of a fiscal year.
 *
 * This class is responsible for validating the data and creating a fiscal year with its related accounting periods.
 *
 * @package Modules\Accounting\Services\PeriodManagement\Commands
 */
final readonly class CreateFiscalYearHandler
{
    public function __construct(
        private readonly AuditLogService          $auditLogService,
        private readonly FiscalYearCreateRules   $rules,
        private readonly AccountingPeriodFactory $periodGenerator,
        private readonly FiscalYearRepository    $fiscalYears,
    )
    {
    }

    /**
     * Handles the creation of a fiscal year.
     *
     * @param CreateFiscalYearData $data The data for creating a fiscal year.
     * @return FiscalYear The created fiscal year with its related accounting periods.
     * @throws ValidationException If there is an overlap with an existing fiscal year.
     */
    public function handle(CreateFiscalYearData $data): FiscalYear
    {
        return DB::transaction(function () use ($data) {
            // Validate the date range
            $this->rules->validateDateRange($data->start, $data->end);

            // Ensure no overlap with existing fiscal years
            $this->rules->ensureNoOverlap($data->start, $data->end);

            // Only one fiscal year can stay open/current in the accounting cycle
            if ($data->isCurrent) {
                $this->fiscalYears->closeAllAndUnsetCurrent();
            }

            $isCurrent = $data->isCurrent;
            $status = $isCurrent
                ? FiscalYearStatus::OPEN->value
                : FiscalYearStatus::CLOSED->value;

            $closedAt = null;
            if (!$isCurrent && $data->end->isPast()) {
                $closedAt = $data->end->copy()->endOfDay()->toDateTimeString();
            }

            // Create the fiscal year
            $fy = $this->fiscalYears->create([
                'code' => $data->code,
                'name' => $data->name,
                'start_date' => $data->start->toDateString(),
                'end_date' => $data->end->toDateString(),
                'status' => $status,
                'is_current' => $isCurrent,
                'closed_at' => $closedAt,
            ]);

            // Generate the twelve monthly periods for the fiscal year
            $this->periodGenerator->generateTwelveMonthlyPeriods($fy, $data->start);

            $fresh = $fy->load('periods');

            $this->auditLogService->recordTenantScoped(
                'fiscal_year.created',
                $fresh,
                null,
                $this->snapshotFiscalYear($fresh),
                [
                    'request_id' => request()->header('X-Request-ID'),
                ]
            );

            return $fresh;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFiscalYear(FiscalYear $fiscalYear): array
    {
        return [
            'id' => (string)$fiscalYear->getKey(),
            'company_id' => (string)($fiscalYear->company_id ?? $fiscalYear->tenant_id),
            'tenant_id' => (string)$fiscalYear->tenant_id,
            'code' => (string)$fiscalYear->code,
            'name' => (string)$fiscalYear->name,
            'start_date' => $fiscalYear->start_date?->toDateString(),
            'end_date' => $fiscalYear->end_date?->toDateString(),
            'status' => $fiscalYear->status->value,
            'is_current' => (bool)$fiscalYear->is_current,
            'closed_at' => $fiscalYear->closed_at?->toISOString(),
            'period_count' => $fiscalYear->periods->count(),
        ];
    }
}

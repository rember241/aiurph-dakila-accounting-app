<?php

namespace Modules\Accounting\Services\PeriodManagement\Commands;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\DTO\PeriodManagement\UpdateFiscalYearData;
use Modules\Accounting\Enums\PeriodManagement\FiscalYearStatus;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Services\CurrentTenantContext;

final class UpdateFiscalYearHandler
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentTenantContext $currentTenantContext
    )
    {
    }

    public function handle(UpdateFiscalYearData $data): FiscalYear
    {
        return DB::transaction(function () use ($data) {
            $tenantId = $this->currentTenantContext->id();

            $fiscalYear = FiscalYear::query()
                ->forTenant($tenantId)
                ->with('periods')
                ->lockForUpdate()
                ->findOrFail($data->fiscalYearId);
            $before = $this->snapshotFiscalYear($fiscalYear);

            $isStatusChanged = $fiscalYear->status !== $data->targetStatus;

            if ($isStatusChanged && $data->targetStatus === FiscalYearStatus::CLOSED) {
                $hasOpenPeriods = $fiscalYear->periods
                    ->contains(fn($period) => $period->status->value !== 'closed');

                if ($hasOpenPeriods) {
                    throw ValidationException::withMessages([
                        'status' => 'Fiscal year status can only be changed when all periods are closed.',
                    ]);
                }
            }

            $fiscalYear->code = $data->code;
            $fiscalYear->name = $data->name;

            if ($isStatusChanged && $data->targetStatus === FiscalYearStatus::CLOSED) {
                $fiscalYear->status = FiscalYearStatus::CLOSED->value;
                $fiscalYear->is_current = false;
                $fiscalYear->closed_at = now();
            }

            if ($isStatusChanged && $data->targetStatus === FiscalYearStatus::OPEN) {
                $fiscalYear->status = FiscalYearStatus::OPEN->value;
                $fiscalYear->closed_at = null;
            }

            $fiscalYear->save();

            $fresh = $fiscalYear->fresh()->loadCount([
                'periods',
                'periods as closed_periods_count' => fn($periodQuery) => $periodQuery->where('status', 'closed'),
            ]);

            $this->auditLogService->recordTenantScoped(
                'fiscal_year.updated',
                $fresh,
                $before,
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
            'status' => $fiscalYear->status->value,
            'is_current' => (bool)$fiscalYear->is_current,
            'closed_at' => $fiscalYear->closed_at?->toISOString(),
            'periods_count' => (int)($fiscalYear->periods_count ?? $fiscalYear->periods->count()),
            'closed_periods_count' => (int)($fiscalYear->closed_periods_count
                ?? $fiscalYear->periods->filter(static fn($period) => $period->status->value === 'closed')->count()),
        ];
    }
}

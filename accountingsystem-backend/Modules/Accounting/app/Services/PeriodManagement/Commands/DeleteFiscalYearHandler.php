<?php

namespace Modules\Accounting\Services\PeriodManagement\Commands;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Services\CurrentTenantContext;

/**
 * Handles fiscal-year deletion with shared-db audit logging.
 */
final class DeleteFiscalYearHandler
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentTenantContext $currentTenantContext
    )
    {
    }

    public function handle(string $fiscalYearId): void
    {
        DB::transaction(function () use ($fiscalYearId): void {
            $fiscalYear = FiscalYear::query()
                ->forTenant($this->currentTenantContext->id())
                ->withCount('periods')
                ->findOrFail($fiscalYearId);

            $before = $this->snapshotFiscalYear($fiscalYear);

            $this->auditLogService->recordTenantScoped(
                'fiscal_year.deleted',
                $fiscalYear,
                $before,
                [],
                [
                    'request_id' => request()->header('X-Request-ID'),
                ]
            );

            $fiscalYear->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFiscalYear(FiscalYear $fiscalYear): array
    {
        return [
            'id' => (string)$fiscalYear->getKey(),
            'tenant_id' => (string)$fiscalYear->tenant_id,
            'code' => (string)$fiscalYear->code,
            'name' => (string)$fiscalYear->name,
            'start_date' => $fiscalYear->start_date?->toDateString(),
            'end_date' => $fiscalYear->end_date?->toDateString(),
            'status' => $fiscalYear->status->value,
            'is_current' => (bool)$fiscalYear->is_current,
            'closed_at' => $fiscalYear->closed_at?->toISOString(),
            'periods_count' => (int)$fiscalYear->periods_count,
        ];
    }
}

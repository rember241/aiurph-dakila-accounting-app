<?php

namespace Modules\Accounting\Services\PeriodManagement\Commands;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\DTO\PeriodManagement\SetCurrentFiscalYearData;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Repositories\PeriodManagement\FiscalYearRepository;
use Throwable;


final class SetCurrentFiscalYearHandler
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly FiscalYearRepository $fiscalYearRepository,
    )
    {
    }

    public function handle(SetCurrentFiscalYearData $data): FiscalYear
    {
        try {
            return DB::transaction(function () use ($data) {
                $fy = $this->fiscalYearRepository->findOrFail($data->fiscalYearId);
                $before = $this->snapshotFiscalYear($fy);

                $this->fiscalYearRepository->closeAllAndUnsetCurrent($fy->id);

                $this->fiscalYearRepository->markAsCurrentAndOpen($fy);

                $fresh = $fy->fresh();

                $this->auditLogService->recordTenantScoped(
                    'fiscal_year.set_current',
                    $fresh,
                    $before,
                    $this->snapshotFiscalYear($fresh),
                    [
                        'request_id' => request()->header('X-Request-ID'),
                    ]
                );

                return $fresh;
            });
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'fiscal_year_id' => 'Fiscal year not found.',
            ]);
        }

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
            'status' => $fiscalYear->status->value,
            'is_current' => (bool)$fiscalYear->is_current,
            'closed_at' => $fiscalYear->closed_at?->toISOString(),
        ];
    }
}

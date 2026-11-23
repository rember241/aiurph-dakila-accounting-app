<?php

namespace Modules\Accounting\Services\PeriodManagement\Commands;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\DTO\PeriodManagement\TransitionPeriodStatusData;
use Modules\Accounting\Enums\PeriodManagement\FiscalYearStatus;
use Modules\Accounting\Enums\PeriodManagement\PeriodStatus;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Services\CurrentTenantContext;

final class TransitionPeriodStatusHandler
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentTenantContext $currentTenantContext
    )
    {
    }

    public function handle(TransitionPeriodStatusData $data): AccountingPeriod
    {
        return DB::transaction(function () use ($data) {
            $tenantId = $this->currentTenantContext->id();

            $period = AccountingPeriod::query()
                ->forTenant($tenantId)
                ->with('fiscalYear')
                ->lockForUpdate()
                ->findOrFail($data->periodId);
            $before = $this->snapshotPeriod($period);

            $current = $period->status;
            $target = $data->targetStatus;

            if (
                $period->fiscalYear !== null &&
                $period->fiscalYear->status->value === 'closed' &&
                $target !== PeriodStatus::CLOSED
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Cannot reopen or soft-close periods under a closed fiscal year.',
                ]);
            }

            if (!$current->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot transition from {$current->value} to {$target->value}.",
                ]);
            }

            $period->status = $target->value;

            match ($target) {
                PeriodStatus::OPEN => $this->appyOpen($period),
                PeriodStatus::SOFT_CLOSED => $this->applySoftClose($period),
                PeriodStatus::CLOSED => $this->applyClose($period),
            };

            $period->save();

            if ($target === PeriodStatus::CLOSED) {
                $this->autoCloseFiscalYearWhenAllPeriodsClosed($period);
            }

            $fresh = $period->refresh()->load('fiscalYear');

            $this->auditLogService->recordTenantScoped(
                'accounting_period.status_transitioned',
                $fresh,
                $before,
                $this->snapshotPeriod($fresh),
                [
                    'request_id' => request()->header('X-Request-ID'),
                    'target_status' => $target->value,
                ]
            );

            return $fresh;
        });
    }

    private function appyOpen(AccountingPeriod $period): void
    {
        $period->lock_ap = false;
        $period->lock_ar = false;
        $period->lock_inventory = false;
        $period->closed_at = null;
    }

    private function applySoftClose(AccountingPeriod $period): void
    {
        $period->lock_ap = true;
        $period->lock_ar = true;
        $period->lock_inventory = true;
        $period->closed_at = null;
    }

    private function applyClose(AccountingPeriod $period): void
    {
        $period->lock_ap = true;
        $period->lock_ar = true;
        $period->lock_inventory = true;
        $period->closed_at = now();
    }

    private function autoCloseFiscalYearWhenAllPeriodsClosed(AccountingPeriod $period): void
    {
        $hasNonClosedPeriods = AccountingPeriod::query()
            ->forTenant($this->currentTenantContext->id())
            ->where('fiscal_year_id', $period->fiscal_year_id)
            ->where('status', '!=', PeriodStatus::CLOSED->value)
            ->exists();

        if ($hasNonClosedPeriods) {
            return;
        }

        $fiscalYear = FiscalYear::query()
            ->forTenant($this->currentTenantContext->id())
            ->lockForUpdate()
            ->find($period->fiscal_year_id);

        if ($fiscalYear === null || $fiscalYear->status === FiscalYearStatus::CLOSED) {
            return;
        }

        $before = $this->snapshotFiscalYear($fiscalYear);
        $fiscalYear->status = FiscalYearStatus::CLOSED->value;
        $fiscalYear->is_current = false;
        $fiscalYear->closed_at = now();
        $fiscalYear->save();

        $this->auditLogService->recordTenantScoped(
            'fiscal_year.auto_closed',
            $fiscalYear,
            $before,
            $this->snapshotFiscalYear($fiscalYear),
            [
                'request_id' => request()->header('X-Request-ID'),
                'trigger_period_id' => (string)$period->getKey(),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotPeriod(AccountingPeriod $period): array
    {
        return [
            'id' => (string)$period->getKey(),
            'tenant_id' => (string)$period->tenant_id,
            'fiscal_year_id' => (string)$period->fiscal_year_id,
            'period_number' => (int)$period->period_number,
            'name' => (string)$period->name,
            'status' => $period->status->value,
            'lock_ap' => (bool)$period->lock_ap,
            'lock_ar' => (bool)$period->lock_ar,
            'lock_inventory' => (bool)$period->lock_inventory,
            'closed_at' => $period->closed_at?->toISOString(),
        ];
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

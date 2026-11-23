<?php

namespace Modules\Accounting\Repositories\PeriodManagement;

use Modules\Accounting\Services\CurrentTenantContext;
use Modules\Accounting\Enums\PeriodManagement\FiscalYearStatus;
use Modules\Accounting\Enums\PeriodManagement\PeriodStatus;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;

final class FiscalYearRepository
{
    public function __construct(
        private readonly CurrentTenantContext $currentTenantContext
    )
    {
    }

    public function closeAllAndUnsetCurrent(?string $exceptFiscalYearId = null): void
    {
        $tenantId = $this->currentTenantContext->id();

        $query = FiscalYear::query()
            ->forTenant($tenantId)
            ->when(
                $exceptFiscalYearId !== null,
                fn($query) => $query->where('id', '!=', $exceptFiscalYearId)
            );

        $fiscalYearIds = (clone $query)->pluck('id');

        $query
            ->update([
                'is_current' => false,
                'status' => FiscalYearStatus::CLOSED->value,
                'closed_at' => now(),
            ]);

        if ($fiscalYearIds->isNotEmpty()) {
            AccountingPeriod::query()
                ->forTenant($tenantId)
                ->whereIn('fiscal_year_id', $fiscalYearIds)
                ->update([
                    'status' => PeriodStatus::CLOSED->value,
                    'lock_ap' => true,
                    'lock_ar' => true,
                    'lock_inventory' => true,
                    'closed_at' => now(),
                ]);
        }
    }

    public function findOrFail(string $id): FiscalYear
    {
        return FiscalYear::query()
            ->forTenant($this->currentTenantContext->id())
            ->findOrFail($id);
    }

    public function create(array $payload): FiscalYear
    {
        $companyId = $this->currentTenantContext->companyId();

        return FiscalYear::query()->create([
            ...$payload,
            'company_id' => $companyId,
            'tenant_id' => $companyId,
        ]);
    }

    public function markAsCurrentAndOpen(FiscalYear $year): FiscalYear
    {
        $year->update([
            'is_current' => true,
            'status' => FiscalYearStatus::OPEN->value,
            'closed_at' => null,
        ]);
        $year->save();

        return $year;
    }

    public function closeAllPeriodsForFiscalYear(string $fiscalYearId): void
    {
        AccountingPeriod::query()
            ->forTenant($this->currentTenantContext->id())
            ->where('fiscal_year_id', $fiscalYearId)
            ->update([
                'status' => PeriodStatus::CLOSED->value,
                'lock_ap' => true,
                'lock_ar' => true,
                'lock_inventory' => true,
                'closed_at' => now(),
            ]);
    }
}

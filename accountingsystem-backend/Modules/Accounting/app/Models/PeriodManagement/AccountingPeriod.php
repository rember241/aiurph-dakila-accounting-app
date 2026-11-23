<?php

namespace Modules\Accounting\Models\PeriodManagement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\HigherOrderCollectionProxy;
use LogicException;
use Modules\Accounting\Database\Factories\AccountingPeriodFactory as AccountingPeriodModelFactory;
use Modules\Accounting\Enums\PeriodManagement\FiscalYearStatus;
use Modules\Accounting\Enums\PeriodManagement\PeriodStatus;
use Modules\Users\Models\Company;
use Modules\Users\Models\Tenant;


/**
 * Represents an accounting period.
 *
 * @property string $id A UUID of the accounting period.
 * @property string $fiscal_year_id A UUID of the fiscal year that the period belongs to.
 * @property int $period_number The period number.
 * @property PeriodStatus $status The status of the accounting period.
 * @property bool $lock_ap Whether the account payable is locked.
 * @property bool $lock_ar Whether the account receivable is locked.
 * @property bool $lock_inventory Whether the inventory is locked.
 * @property Carbon|null $closed_at The date when the accounting period is closed.
 * @property Carbon|null $created_at The date when the accounting period is created.
 * @property Carbon|null $updated_at The date when the accounting period is updated.
 *
 * @package Modules\Accounting\Models\PeriodManagement
 */
class AccountingPeriod extends Model
{
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::saving(function (AccountingPeriod $period): void {
            if (!is_string($period->fiscal_year_id) || trim($period->fiscal_year_id) === '') {
                return;
            }

            $companyId = is_string($period->company_id) ? trim($period->company_id) : '';
            $fiscalYearTenantId = $period->relationLoaded('fiscalYear')
                ? $period->fiscalYear?->tenant_id
                : FiscalYear::query()
                    ->whereKey($period->fiscal_year_id)
                    ->value('tenant_id');
            $fiscalYearCompanyId = $period->relationLoaded('fiscalYear')
                ? $period->fiscalYear?->company_id
                : FiscalYear::query()
                    ->whereKey($period->fiscal_year_id)
                    ->value('company_id');

            if (!is_string($fiscalYearTenantId) || trim($fiscalYearTenantId) === '') {
                return;
            }

            if (
                is_string($period->tenant_id)
                && trim($period->tenant_id) !== ''
                && (string)$period->tenant_id !== (string)$fiscalYearTenantId
            ) {
                throw new LogicException('Accounting period tenant scope must match the related fiscal year tenant.');
            }

            $period->tenant_id = (string)$fiscalYearTenantId;

            if (is_string($fiscalYearCompanyId) && trim($fiscalYearCompanyId) !== '') {
                if ($companyId !== '' && $companyId !== (string) $fiscalYearCompanyId) {
                    throw new LogicException('Accounting period company scope must match the related fiscal year company.');
                }

                $period->company_id = (string) $fiscalYearCompanyId;
            } else {
                $period->company_id = (string) $fiscalYearTenantId;
            }
        });
    }

    protected static function newFactory(): AccountingPeriodModelFactory
    {
        return AccountingPeriodModelFactory::new();
    }

    /**
     * @var false|HigherOrderCollectionProxy|mixed
     */
    protected $fillable = [
        'tenant_id',
        'company_id',
        'fiscal_year_id',
        'period_number',
        'name',
        'start_date',
        'end_date',
        'status',
        'lock_ap',
        'lock_ar',
        'lock_inventory',
        'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'lock_ap' => 'boolean',
        'lock_ar' => 'boolean',
        'lock_inventory' => 'boolean',
        'closed_at' => 'datetime',
        'status' => PeriodStatus::class,
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->forCompany($tenantId);
    }


    /**
     * Determines if the accounting period can accept posting for the given subledger.
     *
     * This method checks if the accounting period is open or soft closed, and if the subledger is among 'ap', 'ar', or 'inventory'.
     * If the accounting period is closed, it returns false. If the accounting period is soft closed and the subledger is among 'ap', 'ar', or 'inventory', it returns false.
     * If the subledger is 'ap', 'ar', or 'inventory', it checks if the corresponding lock is set to false. If it is, it returns true. Otherwise, it returns false.
     * If the subledger is not 'ap', 'ar', 'inventory', it returns true.
     *
     * @param string $subledger The subledger to check.
     * @return bool True if the accounting period can accept posting for the subledger, false otherwise.
     */
    public function canAcceptPosting(string $subledger): bool
    {
        $subledger = strtolower($subledger);

        $fiscalYearStatus = null;

        if ($this->relationLoaded('fiscalYear')) {
            $fiscalYearStatus = $this->fiscalYear?->status;
        } else {
            $fiscalYearQuery = FiscalYear::query()
                ->whereKey($this->fiscal_year_id);

            if (is_string($this->tenant_id) && trim($this->tenant_id) !== '') {
                $fiscalYearQuery->where('tenant_id', $this->tenant_id);
            }

            $fiscalYearStatus = $fiscalYearQuery
                ->value('status');
        }

        $normalizedFiscalYearStatus = $fiscalYearStatus instanceof FiscalYearStatus
            ? $fiscalYearStatus->value
            : (string)$fiscalYearStatus;

        if ($normalizedFiscalYearStatus === FiscalYearStatus::CLOSED->value) {
            return false;
        }

        if ($this->status === PeriodStatus::CLOSED) {
            return false;
        }

        if ($this->status === PeriodStatus::SOFT_CLOSED && in_array($subledger, ['ap', 'ar', 'inventory'], true)) {
            return false;
        }

        return match ($subledger) {
            'ap' => !$this->lock_ap,
            'ar' => !$this->lock_ar,
            'inventory' => !$this->lock_inventory,
            default => true,
        };
    }

}

<?php

namespace Modules\Accounting\Models\PeriodManagement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\FiscalYearFactory;
use Modules\Accounting\Enums\PeriodManagement\FiscalYearStatus;
use Modules\Users\Models\Company;
use Modules\Users\Models\Tenant;


/**
 * Represents a fiscal year.
 *
 * A fiscal year consists of 12 accounting periods, each representing a month.
 * It has a start date and an end date.
 * It has a unique code, a name, and a status.
 * A fiscal year can be set as the current fiscal year.
 * It has many accounting periods.
 *
 *
 * @package Modules\Accounting\Models\PeriodManagement
 */
class FiscalYear extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): FiscalYearFactory
    {
        return FiscalYearFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'company_id',
        'code',
        'name',
        'start_date',
        'end_date',
        'status',
        'is_current',
        'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => FiscalYearStatus::class,
        'is_current' => 'boolean',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (FiscalYear $fiscalYear): void {
            $companyId = is_string($fiscalYear->company_id) ? trim($fiscalYear->company_id) : '';
            $tenantId = is_string($fiscalYear->tenant_id) ? trim($fiscalYear->tenant_id) : '';

            if ($companyId !== '' && $tenantId !== '' && $companyId !== $tenantId) {
                throw new \LogicException('Fiscal year company scope must match the legacy tenant scope.');
            }

            if ($companyId === '' && $tenantId !== '') {
                $fiscalYear->company_id = $tenantId;
            }

            if ($tenantId === '' && $companyId !== '') {
                $fiscalYear->tenant_id = $companyId;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class)->orderBy('period_number');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->forCompany($tenantId);
    }
}

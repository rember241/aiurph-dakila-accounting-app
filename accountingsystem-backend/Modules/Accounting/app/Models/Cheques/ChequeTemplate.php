<?php

namespace Modules\Accounting\Models\Cheques;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Users\Models\Company;

final class ChequeTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id',
        'name',
        'bank_name',
        'width_mm',
        'height_mm',
        'status',
        'is_default',
    ];

    protected $casts = [
        'width_mm' => 'float',
        'height_mm' => 'float',
        'is_default' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ChequeTemplateVersion::class, 'cheque_template_id');
    }

    public function activeVersion()
    {
        return $this->hasOne(ChequeTemplateVersion::class, 'cheque_template_id')
            ->where('is_active', true)
            ->orderByDesc('version');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

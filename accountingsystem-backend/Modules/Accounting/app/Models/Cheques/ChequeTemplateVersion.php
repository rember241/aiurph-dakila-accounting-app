<?php

namespace Modules\Accounting\Models\Cheques;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Users\Models\Company;

final class ChequeTemplateVersion extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id',
        'cheque_template_id',
        'version',
        'date_format',
        'layout',
        'default_print_options',
        'is_active',
    ];

    protected $casts = [
        'version' => 'integer',
        'layout' => 'array',
        'default_print_options' => 'array',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChequeTemplate::class, 'cheque_template_id');
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(ChequePrintLog::class, 'cheque_template_version_id');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

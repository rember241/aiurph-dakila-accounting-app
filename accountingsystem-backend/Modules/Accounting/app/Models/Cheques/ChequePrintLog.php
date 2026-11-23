<?php

namespace Modules\Accounting\Models\Cheques;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\Company;

final class ChequePrintLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id',
        'cheque_disbursement_id',
        'cheque_template_version_id',
        'printed_by',
        'print_type',
        'x_offset_mm',
        'y_offset_mm',
        'reason',
        'payload_snapshot',
        'printed_at',
    ];

    protected $casts = [
        'x_offset_mm' => 'float',
        'y_offset_mm' => 'float',
        'payload_snapshot' => 'array',
        'printed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function chequeDisbursement(): BelongsTo
    {
        return $this->belongsTo(ChequeDisbursement::class, 'cheque_disbursement_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(ChequeTemplateVersion::class, 'cheque_template_version_id');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

<?php

namespace Modules\Accounting\Models\Cheques;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Users\Models\Company;

final class ChequeBook extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'name',
        'bank_name',
        'bank_branch',
        'bank_account_number',
        'cheque_number_prefix',
        'start_number',
        'end_number',
        'next_number',
        'status',
    ];

    protected $casts = [
        'start_number' => 'integer',
        'end_number' => 'integer',
        'next_number' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(ChequeDisbursement::class, 'cheque_book_id');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function nextChequeNumberLabel(): string
    {
        $number = str_pad((string) $this->next_number, strlen((string) $this->end_number), '0', STR_PAD_LEFT);

        return trim((string) $this->cheque_number_prefix) !== ''
            ? (string) $this->cheque_number_prefix.$number
            : $number;
    }
}

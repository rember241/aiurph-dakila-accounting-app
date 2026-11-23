<?php

namespace Modules\Accounting\Models\Cheques;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Users\Models\Company;

final class ChequeDisbursement extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id',
        'cheque_book_id',
        'bank_account_id',
        'cheque_template_version_id',
        'journal_entry_id',
        'reversal_journal_entry_id',
        'cheque_number',
        'cheque_date',
        'payee_name',
        'amount',
        'amount_in_words',
        'crossing_type',
        'bearer_action',
        'reference_no',
        'memo',
        'status',
        'approved_by',
        'approved_at',
        'printed_by',
        'printed_at',
        'posted_at',
        'released_at',
        'voided_at',
        'void_reason',
        'spoiled_at',
        'spoil_reason',
    ];

    protected $casts = [
        'cheque_date' => 'date',
        'amount' => 'float',
        'approved_at' => 'datetime',
        'printed_at' => 'datetime',
        'posted_at' => 'datetime',
        'released_at' => 'datetime',
        'voided_at' => 'datetime',
        'spoiled_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function chequeBook(): BelongsTo
    {
        return $this->belongsTo(ChequeBook::class, 'cheque_book_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(ChequeTemplateVersion::class, 'cheque_template_version_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ChequeDisbursementLine::class, 'cheque_disbursement_id');
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(ChequePrintLog::class, 'cheque_disbursement_id');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

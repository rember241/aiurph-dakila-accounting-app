<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Users\Models\Company;

/**
 * Company-scoped sales invoice payment record.
 *
 * @property string $id
 * @property string $company_id
 * @property string $sales_invoice_id
 * @property \Illuminate\Support\Carbon $payment_date
 * @property float $amount
 * @property string|null $reference_no
 * @property string|null $payment_method
 * @property string|null $payment_channel
 * @property string|null $notes
 * @property string|null $receipt_account_id
 * @property string|null $journal_entry_id
 * @property \Illuminate\Support\Carbon|null $accounting_posted_at
 * @property \Illuminate\Support\Carbon|null $voided_at
 * @property string|null $void_reason
 * @property string|null $reversal_journal_entry_id
 */
final class SalesInvoicePayment extends Model
{
    use HasUuids;

    protected $table = 'sales_invoice_payments';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'sales_invoice_id',
        'payment_date',
        'amount',
        'reference_no',
        'payment_method',
        'payment_channel',
        'notes',
        'receipt_account_id',
        'journal_entry_id',
        'accounting_posted_at',
        'voided_at',
        'void_reason',
        'reversal_journal_entry_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'payment_date' => 'date',
        'amount' => 'float',
        'accounting_posted_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function receiptAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receipt_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isPosted(): bool
    {
        return $this->journal_entry_id !== null && !$this->isVoided();
    }

    public function postingStatus(): string
    {
        if ($this->isVoided()) {
            return 'voided';
        }

        return $this->journal_entry_id !== null ? 'posted' : 'unposted';
    }

    public function canEdit(): bool
    {
        // Once a payment has posted, do not allow silent mutation. Users must
        // void and re-record instead so the journal history stays honest.
        return !$this->isVoided() && $this->journal_entry_id === null;
    }

    public function canVoid(): bool
    {
        return !$this->isVoided() && $this->journal_entry_id !== null;
    }

    public function canDelete(): bool
    {
        // Never allow destructive deletion of a posted receipt — history
        // would drift from the accounting ledger. Unposted rows (legacy or
        // bootstrap) are deletable; voided rows are immutable.
        return !$this->isVoided() && $this->journal_entry_id === null;
    }
}

<?php

namespace Modules\Accounting\Models\JournalEntries;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\Taxes\Tax;

class JournalItem extends Model
{
    use HasUuids;

    /**
     * Automatically cast 'rate' column into a float
     */
    protected $casts = [
        'rate' => 'float',
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'tax_id',
        'description',
        'type',
        'amount',
    ];

    /**
     * One journal item can only be a part of one entry
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * One journal item can only reference 1 account
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * One journal item can only reference one tax rate
     */
    public function tax(): BelongsTo {
        return $this->belongsTo(Tax::class)->withTrashed();
    }
}

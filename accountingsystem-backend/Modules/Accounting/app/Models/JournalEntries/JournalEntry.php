<?php

namespace Modules\Accounting\Models\JournalEntries;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Users\Models\Company;
use Modules\Users\Models\OrgUnit;

class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory()
    {
        return JournalEntryFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'company_id',
        'org_unit_id',
        'entry_date',
        'prefix',
        'entry_number',
        'fund_source_id',
        'reference_number',
        'type',
        'status',
        'notes',
        'amount'
    ];

    protected $casts = [
        'amount' => 'float',
        'entry_date' => 'date:Y-m-d',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    /**
     * A journal entry can have many journal items
     */
    public function journalItems(): hasMany
    {
        return $this->hasMany(JournalItem::class, 'journal_entry_id');
    }

    /**
     * A journal entry can only reference a single fund source
     *
     * @return BelongsTo
     */
    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class)->withTrashed();
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

<?php

namespace Modules\Accounting\Models\Taxes;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Accounting\Models\JournalEntries\JournalItem;

class Tax extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'name',
        'rate',
        'status',
    ];

    /**
     * Automatically cast 'tax' column into a float
     */
    protected $casts = [
        'rate' => 'float',
    ];

    /**
     * A single tax rate can be referenced multiple journal entries
     */
    public function journalItems(): HasMany {
        return $this->hasMany(JournalItem::class);
    }
}

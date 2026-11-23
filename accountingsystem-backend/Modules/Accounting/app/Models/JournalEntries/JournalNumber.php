<?php

namespace Modules\Accounting\Models\JournalEntries;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// TODO: Review if the JournalNumber model needs soft deletes since journal entries are referencing a "snapshot" of a journal number, not their ID
class JournalNumber extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'name',
        'prefix',
        'reset_interval',
        'status',
    ];
}

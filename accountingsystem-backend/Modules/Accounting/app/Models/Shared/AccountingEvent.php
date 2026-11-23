<?php

namespace Modules\Accounting\Models\Shared;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_type',
        'source_module',
        'source_type',
        'source_id',
        'event_date',
        'payload',
        'status',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'event_date' => 'date',
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}


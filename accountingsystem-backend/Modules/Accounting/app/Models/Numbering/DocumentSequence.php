<?php

namespace Modules\Accounting\Models\Numbering;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'module',
        'document_type',
        'prefix',
        'separator',
        'padding',
        'current_number',
        'reset_policy',
        'is_active',
        'last_generated_at',
    ];

    protected $casts = [
        'padding' => 'integer',
        'current_number' => 'integer',
        'is_active' => 'boolean',
        'last_generated_at' => 'datetime',
    ];
}


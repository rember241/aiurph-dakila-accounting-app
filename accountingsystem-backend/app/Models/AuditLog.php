<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\Tenant;


/**
 * AuditLog model
 *
 * This model represents the audit logs of changes made to the application.
 *
 * @package App\Models
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'action',
        'auditable_type',
        'auditable_id',
        'before',
        'after',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'before' => 'array',
        'after' => 'array',
    ];

    /**
     * Actor user that triggered the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Optional tenant scope for the audited action.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}

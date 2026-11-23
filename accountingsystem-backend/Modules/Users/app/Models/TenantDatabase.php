<?php

namespace Modules\Users\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TenantDatabase model.
 *
 * Stores encrypted connection metadata for a tenant's dedicated data store.
 */
class TenantDatabase extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tenant_databases';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'driver',
        'host',
        'port',
        'database_name',
        'database_username',
        'database_password_encrypted',
        'database_schema',
        'status',
        'provisioned_at',
        'last_checked_at',
        'meta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'port' => 'integer',
        'provisioned_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Parent tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}


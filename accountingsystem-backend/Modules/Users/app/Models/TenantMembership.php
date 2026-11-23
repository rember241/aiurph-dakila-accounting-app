<?php

namespace Modules\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * TenantMembership model.
 *
 * Joins one global user to one tenant and tracks membership lifecycle state.
 */
class TenantMembership extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tenant_memberships';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'status',
        'status_before_role_suspension',
        'invited_by_user_id',
        'joined_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'joined_at' => 'datetime',
    ];

    /**
     * Tenant for this membership row.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * User for this membership row.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Inviter user.
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * Roles assigned in this tenant context.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_roles', 'membership_id', 'role_id')
            ->withTimestamps();
    }
}

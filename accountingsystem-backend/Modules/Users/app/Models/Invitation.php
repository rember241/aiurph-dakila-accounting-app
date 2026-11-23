<?php

namespace Modules\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation model.
 *
 * Tracks invitation lifecycle for onboarding a user into a tenant.
 */
class Invitation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'invitations';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'email',
        'role_id',
        'invited_by_user_id',
        'status',
        'token_hash',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'meta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Tenant receiving the invitation.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Optional pre-selected role.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * User who sent the invitation.
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}


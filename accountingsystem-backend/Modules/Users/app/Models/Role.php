<?php

namespace Modules\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role model.
 *
 * Represents a named set of permissions and can be assigned to one or more users.
 *
 * Key fields:
 * - `module_permissions` is the canonical permission source, stored as JSON:
 *   { "module": ["read","write", ...], ... }
 */
class Role extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'roles';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'system_generated',
        'key',
        'name',
        'department',
        'org_unit_id',
        'status',
        'access_level',
        'permission_scope',
        'module_permissions',
        'description',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'module_permissions' => 'array',
        'system_generated' => 'boolean',
    ];

    /**
     * Tenant scope for this role.
     *
     * Null means global/system-level role during transition.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Optional org unit context for this role definition.
     */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    /**
     * Users assigned to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withTimestamps();
    }

    /**
     * Tenant memberships assigned to this role (tenant-aware RBAC path).
     */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(TenantMembership::class, 'membership_roles', 'role_id', 'membership_id')
            ->withTimestamps();
    }
}

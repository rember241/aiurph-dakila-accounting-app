<?php

namespace Modules\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Company organization unit.
 *
 * Org units provide a future-safe company structure boundary for branches,
 * departments, teams, and head office units.
 */
class OrgUnit extends Model
{
    use HasUuids;

    protected $table = 'org_units';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'parent_org_unit_id',
        'type',
        'name',
        'code',
        'location',
        'description',
        'status',
        'is_root',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_root' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_org_unit_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_org_unit_id');
    }

    /**
     * Users whose primary org unit is this record.
     */
    public function primaryUsers(): HasMany
    {
        return $this->hasMany(User::class, 'primary_org_unit_id');
    }

    /**
     * Users explicitly assigned to this org unit.
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_org_unit_assignments', 'org_unit_id', 'user_id')
            ->withPivot(['company_id'])
            ->withTimestamps();
    }

    /**
     * Roles linked to this org unit.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'org_unit_id');
    }
}

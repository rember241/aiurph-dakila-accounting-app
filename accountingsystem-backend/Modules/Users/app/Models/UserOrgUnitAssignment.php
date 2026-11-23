<?php

namespace Modules\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Explicit assignment row linking one user to one org unit inside one company.
 */
class UserOrgUnitAssignment extends Model
{
    protected $table = 'user_org_unit_assignments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'company_id',
        'org_unit_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }
}

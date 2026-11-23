<?php

namespace Modules\Users\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Company model.
 *
 * This is the company-domain alias over the legacy `tenants` storage table.
 * New application code should prefer the company terminology, while legacy
 * tenant-oriented code can continue to operate during the transition.
 */
class Company extends Tenant
{
    /**
     * Company membership rows for this company.
     */
    public function companyMemberships(): HasMany
    {
        return $this->memberships();
    }

    /**
     * Users assigned to this company.
     */
    public function companyUsers(): BelongsToMany
    {
        return $this->users();
    }

    /**
     * Roles defined inside this company.
     */
    public function companyRoles(): HasMany
    {
        return $this->roles();
    }

    /**
     * Organization units defined inside this company.
     */
    public function orgUnits(): HasMany
    {
        return $this->hasMany(OrgUnit::class, 'company_id');
    }
}

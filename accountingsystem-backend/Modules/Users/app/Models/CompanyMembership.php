<?php

namespace Modules\Users\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Company membership model.
 *
 * Company-domain alias over the legacy `tenant_memberships` table.
 */
class CompanyMembership extends TenantMembership
{
    /**
     * Company for this membership row.
     */
    public function company(): BelongsTo
    {
        return $this->tenant();
    }
}

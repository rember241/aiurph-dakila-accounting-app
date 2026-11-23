<?php

namespace Modules\Users\Services\Tenants;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Users\Models\Tenant;
use Modules\Users\Services\Companies\CompanyQueryService;

/**
 * Backward-compatible tenant query wrapper.
 *
 * New code should prefer CompanyQueryService. This wrapper preserves the
 * existing tenant-named controller contract while returning the authenticated
 * user's current company from legacy storage.
 */
class TenantQueryService
{
    public function __construct(
        private readonly CompanyQueryService $companyQueryService
    ) {
    }

    public function paginateVisible(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->companyQueryService->paginateVisible($actor, $filters);
    }

    public function findVisibleOrFail(User $actor, string $tenantId): Tenant
    {
        return $this->companyQueryService->findVisibleOrFail($actor, $tenantId);
    }
}

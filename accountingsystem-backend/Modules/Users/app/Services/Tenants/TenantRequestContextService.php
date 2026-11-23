<?php

namespace Modules\Users\Services\Tenants;

use App\Models\User;
use Modules\Users\Models\Company;
use Modules\Users\Models\Tenant;
use Modules\Users\Services\Companies\CompanyContextService;

/**
 * Backward-compatible tenant context adapter.
 *
 * The underlying organization boundary is now company-centric. This service
 * remains in place so existing tenant-named middleware and controllers can
 * continue working while delegating to company-domain logic.
 */
final class TenantRequestContextService
{
    public const HEADER_NAME = 'X-Tenant-ID';
    public const COMPANY_HEADER_NAME = 'X-Company-ID';

    public function __construct(
        private readonly CompanyContextService $companyContextService
    )
    {
    }

    /**
     * Resolve the legacy storage record by primary key.
     */
    public function resolveFromSharedDatabase(string $tenantId): ?Tenant
    {
        return Company::query()
            ->whereKey($tenantId)
            ->first();
    }

    /**
     * Resolves the authenticated user's current company from legacy tenant storage.
     *
     * The app is single-company per user in the current architecture, so this
     * deliberately does not inspect a requested tenant/company switch target.
     */
    public function resolveCurrentForUser(User $user): ?Tenant
    {
        return $this->companyContextService->resolveCurrentCompany($user);
    }

    /**
     * Determine whether the authenticated user can access the resolved tenant.
     */
    public function userCanAccess(User $user, Tenant $tenant): bool
    {
        return $this->companyContextService->userCanAccess($user, $tenant);
    }

    /**
     * Determine whether the user has the given module ability in the resolved tenant.
     *
     * Owners are treated as tenant admins for the current phase.
     */
    public function userHasAbility(User $user, Tenant $tenant, string $ability): bool
    {
        return $this->companyContextService->userHasAbility($user, $tenant, $ability);
    }

    /**
     * Determine whether the current user should be treated as a tenant super-user.
     */
    public function userHasFullAccess(User $user, Tenant $tenant): bool
    {
        return $this->companyContextService->userHasFullAccess($user, $tenant);
    }

    /**
     * Return the current user's effective tenant abilities in `module.verb` format.
     *
     * @return array<int, string>
     */
    public function userAbilities(User $user, Tenant $tenant): array
    {
        return $this->companyContextService->userAbilities($user, $tenant);
    }
}

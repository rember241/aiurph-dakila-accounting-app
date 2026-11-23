<?php

namespace Modules\Users\Services\Companies;

use App\Models\User;
use App\Support\ModuleAvailability;
use Illuminate\Database\Eloquent\Builder;
use Modules\Users\Models\Company;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Modules\Users\Support\RolePermissionMatrix;

/**
 * Resolves the authenticated user's current company context.
 *
 * Legacy tenant storage remains in place for compatibility, but runtime access
 * now collapses to a single primary company for each user. Header-based company
 * switching is intentionally not supported as part of this transition.
 */
final class CompanyContextService
{
    /**
     * Build the set of companies the user can still access from legacy storage.
     */
    private function visibleCompanyQuery(User $user): Builder
    {
        $userId = (string) $user->getKey();

        return Company::query()->where(function (Builder $query) use ($userId): void {
            $query
                ->where('owner_user_id', $userId)
                ->orWhereHas('memberships', function (Builder $membershipQuery) use ($userId): void {
                    $membershipQuery
                        ->where('user_id', $userId)
                        ->where('status', 'active');
                });
        });
    }

    /**
     * Resolve the current company for the authenticated user.
     *
     * If legacy data still contains multiple companies for a single user, the
     * owner company is preferred and the oldest active membership is used as a
     * deterministic fallback. New flows must not add more than one company.
     */
    public function resolveCurrentCompany(User $user): ?Company
    {
        $directCompanyId = is_string($user->company_id) ? trim($user->company_id) : '';
        if ($directCompanyId !== '') {
            $directCompany = $this->visibleCompanyQuery($user)
                ->whereKey($directCompanyId)
                ->first();

            if ($directCompany instanceof Company) {
                return $directCompany;
            }
        }

        $userId = (string) $user->getKey();

        return $this->visibleCompanyQuery($user)
            ->orderByRaw('CASE WHEN owner_user_id = ? THEN 0 ELSE 1 END', [$userId])
            ->orderBy('created_at', 'asc')
            ->first();
    }

    /**
     * Resolve the user's current company with lightweight relations for API responses.
     */
    public function resolveCurrentCompanyWithRelations(User $user): ?Company
    {
        $company = $this->resolveCurrentCompany($user);
        if (! $company instanceof Company) {
            return null;
        }

        return Company::query()
            ->with([
                'owner:id,name,email',
                'memberships' => fn ($query) => $query
                    ->where('user_id', $user->getKey())
                    ->where('status', 'active')
                    ->with(['roles:id,tenant_id,key,name,status']),
            ])
            ->find($company->getKey());
    }

    /**
     * Determine whether the given company matches the user's resolved company.
     */
    public function userCanAccess(User $user, Tenant $company): bool
    {
        $currentCompany = $this->resolveCurrentCompany($user);

        return $currentCompany instanceof Company
            && (string) $currentCompany->getKey() === (string) $company->getKey();
    }

    /**
     * Determine whether the user has the given module ability in the resolved company.
     */
    public function userHasAbility(User $user, Tenant $company, string $ability): bool
    {
        [$module, $verb] = $this->normalizeAbility($ability);
        if ($module === null || $verb === null) {
            return false;
        }

        if (! ModuleAvailability::isEnabled($module)) {
            return false;
        }

        if (! $this->userCanAccess($user, $company)) {
            return false;
        }

        if ($this->userHasFullAccess($user, $company)) {
            return true;
        }

        $membership = $this->resolveActiveMembership($user, $company);
        if (! $membership instanceof TenantMembership) {
            return false;
        }

        /** @var Role $role */
        foreach ($membership->roles as $role) {
            $modulePermissions = $role->module_permissions ?? [];
            if (! is_array($modulePermissions)) {
                continue;
            }

            $effectivePermissions = $this->expandEffectiveModulePermissions($modulePermissions);
            $verbs = $effectivePermissions[$module] ?? [];
            if ($verbs === []) {
                continue;
            }

            if ($this->moduleGrantsAbility($verbs, $verb)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the actor has owner-level company access.
     */
    public function userHasFullAccess(User $user, Tenant $company): bool
    {
        if (! $this->userCanAccess($user, $company)) {
            return false;
        }

        return (string) $company->owner_user_id === (string) $user->getKey();
    }

    /**
     * Return the actor's effective company abilities in `module.verb` format.
     *
     * @return array<int, string>
     */
    public function userAbilities(User $user, Tenant $company): array
    {
        if (! $this->userCanAccess($user, $company)) {
            return [];
        }

        if ($this->userHasFullAccess($user, $company)) {
            return [];
        }

        $membership = $this->resolveActiveMembership($user, $company);
        if (! $membership instanceof TenantMembership) {
            return [];
        }

        $abilities = [];

        /** @var Role $role */
        foreach ($membership->roles as $role) {
            $modulePermissions = $role->module_permissions ?? [];
            if (! is_array($modulePermissions)) {
                continue;
            }

            foreach ($this->expandEffectiveModulePermissions($modulePermissions) as $normalizedModule => $normalizedVerbs) {
                if ($normalizedVerbs === []) {
                    continue;
                }

                foreach ($normalizedVerbs as $normalizedVerb) {
                    $ability = "{$normalizedModule}.{$normalizedVerb}";
                    [$resolvedModule, $resolvedVerb] = $this->normalizeAbility($ability);

                    if ($resolvedModule === null || $resolvedVerb === null) {
                        continue;
                    }

                    $abilities[$ability] = $ability;
                }

            }
        }

        ksort($abilities);

        return array_values($abilities);
    }

    /**
     * Expand stored module permissions into their effective runtime abilities.
     *
     * @param  array<string, mixed>  $modulePermissions
     * @return array<string, array<int, string>>
     */
    private function expandEffectiveModulePermissions(array $modulePermissions): array
    {
        $expanded = [];

        foreach ($modulePermissions as $module => $verbs) {
            if (! is_string($module)) {
                continue;
            }

            $normalizedModule = strtolower(trim($module));
            if ($normalizedModule === '' || ! RolePermissionMatrix::isModuleEnabled($normalizedModule)) {
                continue;
            }

            $normalizedVerbs = $this->normalizeModuleVerbs($verbs);
            if ($normalizedVerbs === []) {
                continue;
            }

            $expanded[$normalizedModule] = $this->mergeModuleVerbs(
                $expanded[$normalizedModule] ?? [],
                $normalizedVerbs
            );

        }

        ksort($expanded);

        return $expanded;
    }

    private function resolveActiveMembership(User $user, Tenant $company): ?TenantMembership
    {
        return TenantMembership::query()
            ->where('tenant_id', $company->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->with([
                'roles' => fn ($query) => $query
                    ->where('roles.status', 'active')
                    ->where(function (Builder $roleQuery) use ($company): void {
                        $roleQuery
                            ->whereNull('roles.tenant_id')
                            ->orWhere('roles.tenant_id', $company->getKey());
                    }),
            ])
            ->first();
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function normalizeAbility(string $ability): array
    {
        $normalized = strtolower(trim($ability));

        if (preg_match('/^[a-z][a-z0-9_-]*\.[a-z][a-z0-9_-]*$/', $normalized) !== 1) {
            return [null, null];
        }

        [$module, $verb] = explode('.', $normalized, 2);

        return [$module, $verb];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeModuleVerbs(mixed $verbs): array
    {
        if (! is_array($verbs)) {
            return [];
        }

        $normalized = [];

        foreach ($verbs as $verb) {
            if (! is_string($verb)) {
                continue;
            }

            $value = strtolower(trim($verb));

            if ($value === '' || ! in_array($value, RolePermissionMatrix::allowedVerbsForAccessLevel('write'), true)) {
                continue;
            }

            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, string>  $existing
     * @param  array<int, string>  $incoming
     * @return array<int, string>
     */
    private function mergeModuleVerbs(array $existing, array $incoming): array
    {
        $merged = [];

        foreach (array_merge($existing, $incoming) as $verb) {
            $value = strtolower(trim($verb));

            if ($value === '') {
                continue;
            }

            $merged[$value] = $value;
        }

        return array_values($merged);
    }

    /**
     * @param  array<int, string>  $verbs
     */
    private function moduleGrantsAbility(array $verbs, string $requestedVerb): bool
    {
        if (in_array($requestedVerb, $verbs, true)) {
            return true;
        }

        return false;
    }
}

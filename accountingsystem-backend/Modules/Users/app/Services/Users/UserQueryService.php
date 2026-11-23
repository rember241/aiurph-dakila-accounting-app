<?php

namespace Modules\Users\Services\Users;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Users\Models\TenantMembership;
use Modules\Users\Support\DepartmentFilter;

/**
 * UserQueryService
 *
 * Handles user listing and KPI queries.
 */
class UserQueryService
{
    private function scopedMembershipRolesQuery(string $tenantId)
    {
        return function ($query) use ($tenantId): void {
            $query
                ->where('roles.status', 'active')
                ->where(function ($roleQuery) use ($tenantId): void {
                    $roleQuery
                        ->where('roles.tenant_id', $tenantId)
                        ->orWhereNull('roles.tenant_id');
                });
        };
    }

    private function baseListingQuery(string $tenantId, array $filters): Builder
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = (string)($filters['status'] ?? '');
        $department = trim((string)($filters['department'] ?? ''));
        $roleId = (string)($filters['role_id'] ?? '');
        $orgUnitId = trim((string)($filters['org_unit_id'] ?? ''));

        return User::query()
            ->where(function (Builder $query) use ($tenantId): void {
                $query
                    ->where('company_id', $tenantId)
                    ->orWhereHas('tenantMemberships', function ($membershipQuery) use ($tenantId): void {
                        $membershipQuery->where('tenant_id', $tenantId);
                    });
            })
            ->with([
                'tenantMemberships' => function ($membershipQuery) use ($tenantId): void {
                    $membershipQuery
                        ->where('tenant_id', $tenantId)
                        ->with([
                            'roles' => $this->scopedMembershipRolesQuery($tenantId),
                        ]);
                },
                'company:id,name,slug',
                'primaryOrgUnit:id,company_id,parent_org_unit_id,type,name,status,is_root',
                'orgUnits' => fn ($orgUnitQuery) => $orgUnitQuery
                    ->where('org_units.company_id', $tenantId)
                    ->select(['org_units.id', 'org_units.company_id', 'org_units.parent_org_unit_id', 'org_units.type', 'org_units.name', 'org_units.status', 'org_units.is_root']),
            ])
            ->when($q !== '', function ($query) use ($q) {
                $needle = mb_strtolower($q);
                $query->where(function ($where) use ($needle) {
                    $where->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$needle}%"]);
                });
            })
            ->when($status !== '', function ($query) use ($tenantId, $status): void {
                $query->whereHas('tenantMemberships', function ($membershipQuery) use ($tenantId, $status): void {
                    $membershipQuery
                        ->where('tenant_id', $tenantId)
                        ->where('status', $status);
                });
            })
            ->when($department !== '', fn($query) => DepartmentFilter::apply($query, 'department', $department))
            ->when($roleId !== '', function ($query) use ($tenantId, $roleId): void {
                $query->whereHas('tenantMemberships', function ($membershipQuery) use ($tenantId, $roleId): void {
                    $membershipQuery
                        ->where('tenant_id', $tenantId)
                        ->whereHas('roles', fn($roles) => $roles->where('roles.id', $roleId));
                });
            })
            ->when($orgUnitId !== '', function ($query) use ($tenantId, $orgUnitId): void {
                $query->whereHas('orgUnits', function ($orgUnitQuery) use ($tenantId, $orgUnitId): void {
                    $orgUnitQuery
                        ->where('org_units.company_id', $tenantId)
                        ->where('org_units.id', $orgUnitId);
                });
            });
    }

    /**
     * Build paginated user listing with filters and sorting.
     */
    public function paginate(string $tenantId, array $filters): LengthAwarePaginator
    {
        $perPage = (int)($filters['perPage'] ?? 10);
        $perPage = max(5, min($perPage, 100));

        $sortField = (string)($filters['sortField'] ?? 'name');
        $sortDirParam = strtolower((string)($filters['sortDir'] ?? 'asc'));
        $sortDirection = $sortDirParam === 'desc' ? 'desc' : 'asc';

        $sortMap = [
            'name' => 'name',
            'email' => 'email',
            'status' => 'status',
            'department' => 'department',
            'last_active_at' => 'last_active_at',
            'created_at' => 'created_at',
        ];
        $sortColumn = $sortMap[$sortField] ?? 'name';

        return $this->baseListingQuery($tenantId, $filters)
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('name', 'asc')
            ->paginate($perPage);
    }

    /**
     * KPI counts for Users page.
     */
    public function stats(string $tenantId): array
    {
        $query = TenantMembership::query()->where('tenant_id', $tenantId);
        $total = (clone $query)->count();
        $active = (clone $query)->where('status', 'active')->count();
        $inactive = (clone $query)->where('status', 'inactive')->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    public function findVisibleOrFail(string $tenantId, User|string $user): User
    {
        $userId = $user instanceof User ? (string) $user->getKey() : (string) $user;

        return $this->baseListingQuery($tenantId, [])
            ->whereKey($userId)
            ->firstOrFail();
    }
}

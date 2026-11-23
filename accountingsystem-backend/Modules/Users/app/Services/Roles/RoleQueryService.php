<?php

namespace Modules\Users\Services\Roles;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Users\Models\Role;
use Modules\Users\Support\DepartmentFilter;

/**
 * RoleQueryService
 *
 * Handles role listing and KPI queries.
 */
class RoleQueryService
{
    private function baseListingQuery(array $filters): Builder
    {
        $q = trim((string)($filters['q'] ?? ''));
        $companyId = trim((string)($filters['company_id'] ?? $filters['tenant_id'] ?? ''));
        $status = (string)($filters['status'] ?? '');
        $department = trim((string)($filters['department'] ?? ''));
        $orgUnitId = trim((string)($filters['org_unit_id'] ?? ''));

        return Role::query()
            ->with(['orgUnit:id,company_id,parent_org_unit_id,type,name,status,is_root'])
            ->withCount(['users', 'memberships'])
            ->when($q !== '', function ($query) use ($q) {
                $needle = mb_strtolower($q);

                $query->where(function ($where) use ($needle) {
                    $where->whereRaw('LOWER(key) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(name) LIKE ?', ["%{$needle}%"]);
                });
            })
            ->when($companyId !== '', fn($query) => $query->where('tenant_id', $companyId))
            ->when($status !== '', fn($query) => $query->where('status', $status))
            ->when($department !== '', fn($query) => DepartmentFilter::apply($query, 'department', $department))
            ->when($orgUnitId !== '', fn($query) => $query->where('org_unit_id', $orgUnitId));
    }

    /**
     * Build paginated role listing with filters and sorting.
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int)($filters['perPage'] ?? 10);
        $perPage = max(5, min($perPage, 100));

        $sortField = (string)($filters['sortField'] ?? 'name');
        $sortDirParam = strtolower((string)($filters['sortDir'] ?? 'asc'));
        $sortDirection = $sortDirParam === 'desc' ? 'desc' : 'asc';

        $sortMap = [
            'name' => 'name',
            'key' => 'key',
            'status' => 'status',
            'department' => 'department',
            'updated_at' => 'updated_at',
            'created_at' => 'created_at',
        ];
        $sortColumn = $sortMap[$sortField] ?? 'name';

        return $this->baseListingQuery($filters)
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('name', 'asc')
            ->paginate($perPage);
    }

    /**
     * KPI counts for Roles page.
     */
    public function stats(array $filters = []): array
    {
        $query = $this->baseListingQuery($filters);
        $total = (clone $query)->count();
        $active = (clone $query)->where('status', 'active')->count();
        $inactive = (clone $query)->where('status', 'inactive')->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }
}

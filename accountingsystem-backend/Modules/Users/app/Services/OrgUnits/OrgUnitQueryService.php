<?php

namespace Modules\Users\Services\OrgUnits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Users\Models\OrgUnit;

/**
 * Handles company-scoped organization unit reads.
 */
final class OrgUnitQueryService
{
    private function baseListingQuery(string $companyId, array $filters): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $type = trim((string) ($filters['type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $parentOrgUnitId = trim((string) ($filters['parent_org_unit_id'] ?? ''));
        $rootOnly = filter_var($filters['root_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return OrgUnit::query()
            ->with(['parent:id,name,type,status'])
            ->withCount([
                'children',
                'assignedUsers as assigned_users_count',
                'primaryUsers as primary_users_count',
                'roles as linked_roles_count',
            ])
            ->where('company_id', $companyId)
            ->when($q !== '', function (Builder $query) use ($q): void {
                $needle = mb_strtolower($q);

                $query->where(function (Builder $nestedQuery) use ($needle): void {
                    $nestedQuery
                        ->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(code, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(location, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ["%{$needle}%"]);
                });
            })
            ->when($type !== '', fn (Builder $query) => $query->where('type', $type))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($parentOrgUnitId !== '', fn (Builder $query) => $query->where('parent_org_unit_id', $parentOrgUnitId))
            ->when($rootOnly, fn (Builder $query) => $query->where('is_root', true));
    }

    public function paginate(string $companyId, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['perPage'] ?? 10);
        $perPage = max(5, min($perPage, 100));

        $sortField = (string) ($filters['sortField'] ?? 'name');
        $sortDirParam = strtolower((string) ($filters['sortDir'] ?? 'asc'));
        $sortDirection = $sortDirParam === 'desc' ? 'desc' : 'asc';

        $sortMap = [
            'name' => 'name',
            'type' => 'type',
            'status' => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $sortColumn = $sortMap[$sortField] ?? 'name';

        return $this->baseListingQuery($companyId, $filters)
            ->orderByDesc('is_root')
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('name', 'asc')
            ->paginate($perPage);
    }

    /**
     * Resolve a company-scoped org unit or fail with a tenant-safe 404.
     */
    public function findVisibleOrFail(string $companyId, OrgUnit|string $orgUnit): OrgUnit
    {
        $orgUnitId = $orgUnit instanceof OrgUnit ? (string) $orgUnit->getKey() : (string) $orgUnit;

        $resolved = OrgUnit::query()
            ->with(['parent:id,name,type,status'])
            ->withCount([
                'children',
                'assignedUsers as assigned_users_count',
                'primaryUsers as primary_users_count',
                'roles as linked_roles_count',
            ])
            ->where('company_id', $companyId)
            ->whereKey($orgUnitId)
            ->first();

        if (!$resolved instanceof OrgUnit) {
            throw (new ModelNotFoundException())->setModel(OrgUnit::class, [$orgUnitId]);
        }

        return $resolved;
    }

    public function stats(string $companyId): array
    {
        $query = OrgUnit::query()->where('company_id', $companyId);

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'inactive' => (clone $query)->where('status', 'inactive')->count(),
            'root' => (clone $query)->where('is_root', true)->count(),
        ];
    }
}

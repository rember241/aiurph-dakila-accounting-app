<?php

namespace Modules\Users\Services\Companies;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Users\Models\Company;

/**
 * Handles company reads inside the new single-company-per-user model.
 */
final class CompanyQueryService
{
    public function __construct(
        private readonly CompanyContextService $companyContextService
    ) {
    }

    private function currentCompanyQuery(User $actor, array $filters): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $currentCompany = $this->companyContextService->resolveCurrentCompany($actor);

        return Company::query()
            ->with([
                'owner:id,name,email',
                'memberships' => fn ($query) => $query
                    ->where('user_id', $actor->getKey())
                    ->where('status', 'active')
                    ->with(['roles:id,tenant_id,key,name,status']),
            ])
            ->when(
                $currentCompany instanceof Company,
                fn (Builder $query) => $query->whereKey($currentCompany->getKey()),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            )
            ->when($q !== '', function (Builder $query) use ($q): void {
                $needle = mb_strtolower($q);

                $query->where(function (Builder $where) use ($needle): void {
                    $where->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(slug) LIKE ?', ["%{$needle}%"]);
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status));
    }

    /**
     * Paginate the authenticated user's current company.
     */
    public function paginateVisible(User $actor, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['perPage'] ?? 10);
        $perPage = max(5, min($perPage, 100));

        $sortField = (string) ($filters['sortField'] ?? 'name');
        $sortDirParam = strtolower((string) ($filters['sortDir'] ?? 'asc'));
        $sortDirection = $sortDirParam === 'desc' ? 'desc' : 'asc';

        $sortMap = [
            'name' => 'name',
            'slug' => 'slug',
            'status' => 'status',
            'timezone' => 'timezone',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $sortColumn = $sortMap[$sortField] ?? 'name';

        return $this->currentCompanyQuery($actor, $filters)
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('name', 'asc')
            ->paginate($perPage);
    }

    /**
     * Resolve the authenticated user's current visible company or fail.
     */
    public function findVisibleOrFail(User $actor, string $companyId): Company
    {
        $company = $this->currentCompanyQuery($actor, [])
            ->whereKey($companyId)
            ->first();

        if (!$company instanceof Company) {
            throw (new ModelNotFoundException())->setModel(Company::class, [$companyId]);
        }

        return $company;
    }

    /**
     * Resolve the current company snapshot for the authenticated user.
     */
    public function currentForUser(User $actor): ?Company
    {
        return $this->companyContextService->resolveCurrentCompanyWithRelations($actor);
    }
}

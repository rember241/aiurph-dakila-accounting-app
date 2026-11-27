<?php

namespace Modules\Accounting\Services\ChartOfAccounts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountCategory;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Services\CurrentTenantContext;

class ChartOfAccountsQueryService
{
    private const ROOT_KEY = '__root__';
    private const ACCOUNT_TYPE_ORDER = [
        'asset' => 1,
        'liability' => 2,
        'equity' => 3,
        'revenue' => 4,
        'expense' => 5,
    ];

    public function __construct(
        private readonly CurrentTenantContext $currentTenantContext
    )
    {
    }

    private function parseBooleanFilter(array $filters, string $key): ?bool
    {
        if (!array_key_exists($key, $filters) || $filters[$key] === '' || $filters[$key] === null) {
            return null;
        }

        $raw = strtolower((string)$filters[$key]);
        if (in_array($raw, ['1', 'true', 't'], true)) {
            return true;
        }

        if (in_array($raw, ['0', 'false', 'f'], true)) {
            return false;
        }

        return null;
    }

    private function baseListingQuery(array $filters): Builder
    {
        $companyId = $this->currentTenantContext->companyId();
        $q = trim((string)($filters['q'] ?? ''));
        $type = (string)($filters['type'] ?? '');
        $status = (string)($filters['status'] ?? '');
        $category = AccountCategory::tryFrom((string) ($filters['category'] ?? ''));
        $isPosting = $this->parseBooleanFilter($filters, 'is_posting');

        return Account::query()
            ->from('accounts as a')
            ->leftJoin('accounts as p', function ($join): void {
                $join->on('p.id', '=', 'a.parent_id')
                    ->on('p.company_id', '=', 'a.company_id');
            })
            ->leftJoin('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->leftJoin('detail_types as dt', 'dt.id', '=', 'a.detail_type_id')
            ->select([
                'a.id',
                'a.company_id',
                'a.tenant_id',
                'a.code',
                'a.name',
                'a.type',
                'a.account_type_id',
                'a.detail_type_id',
                'a.parent_id',
                'a.is_posting',
                'a.status',
                'a.normal_balance',
                'a.is_contra',
                'a.contra_account_id',
                'a.description',
                'p.code as parent_code',
                'p.name as parent_name',
                'at.name as account_type_name',
                'dt.name as detail_type_name',
                'a.created_at',
                'a.updated_at',
                'a.is_system'
            ])
            ->where('a.company_id', $companyId)
            ->when($q !== '', function ($query) use ($q) {
                $needle = mb_strtolower($q);

                $query->where(function ($where) use ($needle) {
                    $where->whereRaw('LOWER(a.name) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(a.code) LIKE ?', ["%{$needle}%"]);
                });
            })
            ->when($type !== '', fn($query) => $query->where('a.type', $type))
            ->when($category, function (Builder $query, AccountCategory $category): void {
                $legacyNames = $category->legacyNames();

                $query->where('a.type', $category->coreType())
                    ->where(function (Builder $matches) use ($category, $legacyNames): void {
                        $matches->whereIn('dt.code', $category->detailCodes())
                            ->orWhereIn(DB::raw('LOWER(a.name)'), $legacyNames)
                            ->orWhereIn(DB::raw('LOWER(p.name)'), $legacyNames);

                        if ($category === AccountCategory::Bank) {
                            // The workbook catalog groups bank accounts with cash on hand.
                            $matches->orWhere(function (Builder $legacyBank): void {
                                $legacyBank->where('dt.code', 'cash-on-hand-and-in-banks')
                                    ->whereRaw('LOWER(a.name) LIKE ?', ['cib-%']);
                            });
                        }
                    });
            })
            ->when($status !== '', fn($query) => $query->where('a.status', $status))
            ->when($isPosting !== null, fn($query) => $query->where('a.is_posting', $isPosting));
    }

    private function statementGroupFromType(string $type): string
    {
        return in_array($type, ['asset', 'liability', 'equity'], true)
            ? 'balance_sheet'
            : 'income_statement';
    }

    private function typeOrderExpression(string $alias = 'a'): string
    {
        $cases = [];

        foreach (self::ACCOUNT_TYPE_ORDER as $type => $order) {
            $cases[] = "WHEN '{$type}' THEN {$order}";
        }

        return sprintf('CASE %s.type %s ELSE 99 END', $alias, implode(' ', $cases));
    }

    private function mapRowToNode(object $row): array
    {
        return [
            'id' => (string)$row->id,
            'code' => (string)$row->code,
            'name' => (string)$row->name,
            'type' => (string)$row->type,
            'account_type_id' => $row->account_type_id,
            'account_type_name' => $row->account_type_name,
            'detail_type_id' => $row->detail_type_id,
            'detail_type_name' => $row->detail_type_name,
            'parent_id' => $row->parent_id,
            'is_posting' => (bool)$row->is_posting,
            'status' => (string)$row->status,
            'normal_balance' => (string)$row->normal_balance,
            'is_contra' => (bool)$row->is_contra,
            'is_system' => (bool)$row->is_system,
            'statement_group' => $this->statementGroupFromType((string)$row->type),
            'description' => $row->description,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'children' => [],
        ];
    }

    private function buildTree(array $nodesByParent, string $parentKey): array
    {
        $items = $nodesByParent[$parentKey] ?? [];
        $tree = [];

        foreach ($items as $item) {
            $item['children'] = $this->buildTree($nodesByParent, (string)$item['id']);
            $tree[] = $item;
        }

        return $tree;
    }

    /**
     * Build paginated account listing with filters and sorting.
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int)($filters['perPage'] ?? 10);
        $perPage = max(5, min($perPage, 100));

        $sortField = (string)($filters['sortField'] ?? 'name');
        $sortDirParam = strtolower((string)($filters['sortDir'] ?? 'asc'));
        $sortDirection = $sortDirParam === 'desc' ? 'desc' : 'asc';

        // Explicit sort map to keep orderBy safe and support joined lookup names.
        $sortMap = [
            'code' => 'a.code',
            'name' => 'a.name',
            'status' => 'a.status',
            'is_posting' => 'a.is_posting',
            'normal_balance' => 'a.normal_balance',
            'created_at' => 'a.created_at',
            'account_type_name' => 'at.name',
            'detail_type_name' => 'dt.name',
        ];
        $sortColumn = $sortMap[$sortField] ?? 'a.name';

        $query = $this->baseListingQuery($filters);

        if ($sortField === 'type') {
            return $query
                ->orderByRaw($this->typeOrderExpression().' '.$sortDirection)
                ->orderBy('a.code', 'asc')
                ->orderBy('a.name', 'asc')
                ->paginate($perPage);
        }

        return $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('a.name', 'asc')
            ->paginate($perPage);
    }

    /**
     * Build nested account tree using parent_id hierarchy.
     *
     * @return array{tree: array<int, array>, total: int}
     */
    public function tree(array $filters): array
    {
        $rows = $this->baseListingQuery($filters)
            ->orderByRaw($this->typeOrderExpression().' asc')
            ->orderBy('a.code', 'asc')
            ->orderBy('a.name', 'asc')
            ->get();

        $visibleIds = $rows->keyBy('id');
        $nodesByParent = [];
        foreach ($rows as $row) {
            $parentKey = $row->parent_id !== null && $visibleIds->has($row->parent_id)
                ? (string) $row->parent_id
                : self::ROOT_KEY;
            $nodesByParent[$parentKey][] = $this->mapRowToNode($row);
        }

        return [
            'tree' => $this->buildTree($nodesByParent, self::ROOT_KEY),
            'total' => $rows->count(),
        ];
    }

    /**
     * Resolve one tenant-scoped account or fail with not found.
     */
    public function findOrFail(string $accountId): Account
    {
        $tenantId = $this->currentTenantContext->id();

        $account = Account::query()
            ->forCompany($tenantId)
            ->find($accountId);

        if (!$account instanceof Account) {
            throw (new ModelNotFoundException())->setModel(Account::class, [$accountId]);
        }

        return $account;
    }


    public function stats(): array
    {
        $tenantId = $this->currentTenantContext->id();
        $tenantAccounts = Account::query()->forCompany($tenantId);

        $total = (clone $tenantAccounts)->count();
        $posting = (clone $tenantAccounts)->where('is_posting', true)->count();
        $nonPosting = (clone $tenantAccounts)->where('is_posting', false)->count();
        $active = (clone $tenantAccounts)->where('status', 'active')->count();
        $inactive = (clone $tenantAccounts)->where('status', 'inactive')->count();

        $byType = Account::query()
            ->forCompany($tenantId)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return [
            'total' => $total,
            'posting' => $posting,
            'nonPosting' => $nonPosting,
            'active' => $active,
            'inactive' => $inactive,
            'byType' => $byType,
        ];
    }


}

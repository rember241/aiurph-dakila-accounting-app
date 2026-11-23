<?php

namespace Modules\Inventory\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;

/**
 * Handles company-scoped inventory item read queries.
 */
final class InventoryItemQueryService
{
    private function baseListingQuery(string $companyId, array $filters): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $itemType = trim((string) ($filters['item_type'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));

        return InventoryItem::query()
            ->forCompany($companyId)
            ->when($q !== '', function (Builder $query) use ($q): void {
                $needle = mb_strtolower($q);

                $query->where(function (Builder $nestedQuery) use ($needle): void {
                    $nestedQuery
                        ->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(code) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(category, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(unit_of_measure) LIKE ?', ["%{$needle}%"]);
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($itemType !== '', fn (Builder $query) => $query->where('item_type', $itemType))
            ->when($category !== '', function (Builder $query) use ($category): void {
                $query->whereRaw('LOWER(COALESCE(category, \'\')) = ?', [mb_strtolower($category)]);
            });
    }

    public function paginate(string $companyId, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['perPage'] ?? 10);
        $perPage = max(5, min($perPage, 100));

        $sortField = (string) ($filters['sortField'] ?? 'name');
        $sortDirParam = strtolower((string) ($filters['sortDir'] ?? 'asc'));
        $sortDirection = $sortDirParam === 'desc' ? 'desc' : 'asc';

        $sortMap = [
            'code' => 'code',
            'name' => 'name',
            'status' => 'status',
            'item_type' => 'item_type',
            'unit_of_measure' => 'unit_of_measure',
            'quantity_on_hand' => 'quantity_on_hand',
            'sales_price' => 'sales_price',
            'cost_price' => 'cost_price',
            'reorder_point' => 'reorder_point',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $sortColumn = $sortMap[$sortField] ?? 'name';

        return $this->baseListingQuery($companyId, $filters)
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('name', 'asc')
            ->paginate($perPage);
    }

    /**
     * Returns dashboard counts for the item list using the same filters.
     *
     * Keeping the KPI query tied to the listing filters prevents the front-end
     * from showing totals that do not match the current search/status/type view.
     *
     * @return array<string, int|float>
     */
    public function stats(string $companyId, array $filters): array
    {
        $query = $this->baseListingQuery($companyId, $filters);
        $inventoryValueTotal = (clone $query)
            ->where('item_type', 'inventory')
            ->value(DB::raw('COALESCE(SUM(quantity_on_hand * cost_price), 0)'));

        return [
            'total' => (int) (clone $query)->count(),
            'active' => (int) (clone $query)->where('status', 'active')->count(),
            'inactive' => (int) (clone $query)->where('status', 'inactive')->count(),
            'inventory_count' => (int) (clone $query)->where('item_type', 'inventory')->count(),
            'non_inventory_count' => (int) (clone $query)->where('item_type', 'non_inventory')->count(),
            'service_count' => (int) (clone $query)->where('item_type', 'service')->count(),
            'quantity_on_hand_total' => (float) ((clone $query)->where('item_type', 'inventory')->sum('quantity_on_hand') ?: 0),
            'inventory_value_total' => (float) ($inventoryValueTotal ?: 0),
            'low_stock_count' => (int) (clone $query)
                ->where('status', 'active')
                ->where('item_type', 'inventory')
                ->whereNotNull('reorder_point')
                ->whereColumn('quantity_on_hand', '<=', 'reorder_point')
                ->count(),
        ];
    }

    /**
     * Resolves a company-scoped item or fails with a company-safe 404.
     *
     * Controllers call this after route model binding because Laravel's default
     * binding only knows the ID, not whether the item belongs to the current
     * company.
     */
    public function findVisibleOrFail(string $companyId, InventoryItem|string $inventoryItem): InventoryItem
    {
        $inventoryItemId = $inventoryItem instanceof InventoryItem
            ? (string) $inventoryItem->getKey()
            : (string) $inventoryItem;

        $resolved = InventoryItem::query()
            ->forCompany($companyId)
            ->whereKey($inventoryItemId)
            ->first();

        if (!$resolved instanceof InventoryItem) {
            throw (new ModelNotFoundException())->setModel(InventoryItem::class, [$inventoryItemId]);
        }

        return $resolved;
    }
}

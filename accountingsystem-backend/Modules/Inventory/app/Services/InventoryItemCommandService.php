<?php

namespace Modules\Inventory\Services;

use App\Services\Audit\AuditLogService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;
use Modules\Sales\Models\SalesInvoiceLine;
use RuntimeException;
use Throwable;

/**
 * Handles company-scoped inventory item write operations.
 */
final class InventoryItemCommandService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentCompanyContext $currentCompanyContext
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @throws Throwable
     */
    public function create(array $payload): InventoryItem
    {
        $companyId = $this->currentCompanyContext->companyId();
        $normalizedPayload = $this->normalizePayload($payload);

        $inventoryItem = DB::transaction(function () use ($companyId, $normalizedPayload): InventoryItem {
            return InventoryItem::query()->create([
                'company_id' => $companyId,
                'code' => (string) $normalizedPayload['code'],
                'name' => (string) $normalizedPayload['name'],
                'description' => $normalizedPayload['description'] ?? null,
                'status' => (string) ($normalizedPayload['status'] ?? 'active'),
                'unit_of_measure' => (string) $normalizedPayload['unit_of_measure'],
                'quantity_on_hand' => (float) ($normalizedPayload['quantity_on_hand'] ?? 0),
                'sales_price' => $normalizedPayload['sales_price'],
                'cost_price' => $normalizedPayload['cost_price'],
                'pricing_as_of' => $normalizedPayload['pricing_as_of'] ?? null,
                'stock_as_of' => $normalizedPayload['stock_as_of'] ?? null,
                'reorder_point' => $normalizedPayload['reorder_point'],
                'item_type' => (string) $normalizedPayload['item_type'],
                'category' => $normalizedPayload['category'] ?? null,
            ]);
        });

        $this->auditLogService->recordCompanyScoped(
            'inventory_item.created',
            $inventoryItem,
            null,
            $this->snapshot($inventoryItem),
            [
                'company_id' => (string) $inventoryItem->company_id,
                'request_id' => request()->header('X-Request-ID'),
            ]
        );

        return $inventoryItem;
    }

    /**
     * @param array<string, mixed> $payload
     * @throws Throwable
     */
    public function update(InventoryItem $inventoryItem, array $payload): InventoryItem
    {
        $this->ensureCurrentCompanyScope($inventoryItem);
        $normalizedPayload = $this->normalizePayload($payload, true, (string) $inventoryItem->item_type);

        $before = $this->snapshot($inventoryItem);

        $updatedItem = DB::transaction(function () use ($inventoryItem, $normalizedPayload): InventoryItem {
            $inventoryItem->fill([
                'code' => array_key_exists('code', $normalizedPayload) ? (string) $normalizedPayload['code'] : $inventoryItem->code,
                'name' => array_key_exists('name', $normalizedPayload) ? (string) $normalizedPayload['name'] : $inventoryItem->name,
                'description' => array_key_exists('description', $normalizedPayload) ? $normalizedPayload['description'] : $inventoryItem->description,
                'status' => array_key_exists('status', $normalizedPayload) ? (string) $normalizedPayload['status'] : $inventoryItem->status,
                'unit_of_measure' => array_key_exists('unit_of_measure', $normalizedPayload) ? (string) $normalizedPayload['unit_of_measure'] : $inventoryItem->unit_of_measure,
                'quantity_on_hand' => array_key_exists('quantity_on_hand', $normalizedPayload) ? (float) $normalizedPayload['quantity_on_hand'] : $inventoryItem->quantity_on_hand,
                'sales_price' => array_key_exists('sales_price', $normalizedPayload) ? $normalizedPayload['sales_price'] : $inventoryItem->sales_price,
                'cost_price' => array_key_exists('cost_price', $normalizedPayload) ? $normalizedPayload['cost_price'] : $inventoryItem->cost_price,
                'pricing_as_of' => array_key_exists('pricing_as_of', $normalizedPayload) ? $normalizedPayload['pricing_as_of'] : $inventoryItem->pricing_as_of,
                'stock_as_of' => array_key_exists('stock_as_of', $normalizedPayload) ? $normalizedPayload['stock_as_of'] : $inventoryItem->stock_as_of,
                'reorder_point' => array_key_exists('reorder_point', $normalizedPayload) ? $normalizedPayload['reorder_point'] : $inventoryItem->reorder_point,
                'item_type' => array_key_exists('item_type', $normalizedPayload) ? (string) $normalizedPayload['item_type'] : $inventoryItem->item_type,
                'category' => array_key_exists('category', $normalizedPayload) ? $normalizedPayload['category'] : $inventoryItem->category,
            ]);

            if ($inventoryItem->isDirty()) {
                $inventoryItem->save();
            }

            return $inventoryItem->fresh();
        });

        if ($before !== $this->snapshot($updatedItem)) {
            $this->auditLogService->recordCompanyScoped(
                'inventory_item.updated',
                $updatedItem,
                $before,
                $this->snapshot($updatedItem),
                [
                    'company_id' => (string) $updatedItem->company_id,
                    'request_id' => request()->header('X-Request-ID'),
                ]
            );
        }

        return $updatedItem;
    }

    /**
     * @throws Throwable
     */
    public function transition(InventoryItem $inventoryItem, string $status): InventoryItem
    {
        $this->ensureCurrentCompanyScope($inventoryItem);

        $normalizedStatus = strtolower(trim($status));
        if ($normalizedStatus === '' || $normalizedStatus === (string) $inventoryItem->status) {
            return $inventoryItem->fresh() ?? $inventoryItem;
        }

        $before = ['status' => (string) $inventoryItem->status];

        $updatedItem = DB::transaction(function () use ($inventoryItem, $normalizedStatus): InventoryItem {
            $inventoryItem->update(['status' => $normalizedStatus]);

            return $inventoryItem->fresh();
        });

        $event = $normalizedStatus === 'active'
            ? 'inventory_item.activated'
            : 'inventory_item.deactivated';

        $this->auditLogService->recordCompanyScoped(
            $event,
            $updatedItem,
            $before,
            ['status' => (string) $updatedItem->status],
            [
                'company_id' => (string) $updatedItem->company_id,
                'request_id' => request()->header('X-Request-ID'),
            ]
        );

        return $updatedItem;
    }

    /**
     * @throws Throwable
     */
    public function delete(InventoryItem $inventoryItem): void
    {
        $this->ensureCurrentCompanyScope($inventoryItem);
        $this->ensureCanBeDeleted($inventoryItem);

        try {
            DB::transaction(function () use ($inventoryItem): void {
                $before = $this->snapshot($inventoryItem);

                $this->auditLogService->recordCompanyScoped(
                    'inventory_item.deleted',
                    $inventoryItem,
                    $before,
                    [],
                    [
                        'company_id' => (string) $inventoryItem->company_id,
                        'request_id' => request()->header('X-Request-ID'),
                    ]
                );

                $inventoryItem->delete();
            });
        } catch (QueryException $exception) {
            if (!$this->isDeleteConstraintException($exception)) {
                throw $exception;
            }

            throw new RuntimeException($this->buildDeleteConflictMessage($inventoryItem));
        }
    }

    private function ensureCurrentCompanyScope(InventoryItem $inventoryItem): void
    {
        $companyId = $this->currentCompanyContext->companyId();

        if ((string) $inventoryItem->company_id === $companyId) {
            return;
        }

        abort(404, 'Inventory item not found.');
    }

    private function ensureCanBeDeleted(InventoryItem $inventoryItem): void
    {
        $reasons = $this->deleteConflictReasons($inventoryItem);

        if ($reasons !== []) {
            throw new RuntimeException($this->buildDeleteConflictMessage($inventoryItem, $reasons));
        }
    }

    private function deleteIsBlockedBySalesInvoices(InventoryItem $inventoryItem): bool
    {
        return SalesInvoiceLine::query()
            ->where('company_id', (string) $inventoryItem->company_id)
            ->where('inventory_item_id', (string) $inventoryItem->getKey())
            ->exists();
    }

    private function deleteIsBlockedByStockHistory(InventoryItem $inventoryItem): bool
    {
        return $inventoryItem->movements()->exists();
    }

    /**
     * @return array<int, string>
     */
    private function deleteConflictReasons(InventoryItem $inventoryItem): array
    {
        $reasons = [];

        if ($this->deleteIsBlockedBySalesInvoices($inventoryItem)) {
            $reasons[] = 'it is already used on one or more sales invoices';
        }

        if ($this->deleteIsBlockedByStockHistory($inventoryItem)) {
            $reasons[] = 'stock activity has already been recorded for it';
        }

        return $reasons;
    }

    /**
     * @param array<int, string>|null $reasons
     */
    private function buildDeleteConflictMessage(InventoryItem $inventoryItem, ?array $reasons = null): string
    {
        $reasons ??= $this->deleteConflictReasons($inventoryItem);

        if ($reasons === []) {
            $reasons[] = 'it is still linked to other records in the system';
        }

        $label = trim((string) $inventoryItem->name);
        $itemName = $label !== '' ? $label : 'This item';

        if (count($reasons) === 1) {
            return sprintf(
                "%s can't be deleted because %s. Mark it as inactive instead if you no longer want to use it.",
                $itemName,
                $reasons[0]
            );
        }

        return sprintf(
            "%s can't be deleted because %s and %s. Mark it as inactive instead if you no longer want to use it.",
            $itemName,
            $reasons[0],
            $reasons[1]
        );
    }

    private function isDeleteConstraintException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return (string) $exception->getCode() === '23503'
            || str_contains($message, 'violates foreign key constraint')
            || str_contains($message, 'foreign key')
            || str_contains($message, 'restrict on delete');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(InventoryItem $inventoryItem): array
    {
        return [
            'id' => (string) $inventoryItem->getKey(),
            'company_id' => (string) $inventoryItem->company_id,
            'code' => (string) $inventoryItem->code,
            'name' => (string) $inventoryItem->name,
            'description' => $inventoryItem->description,
            'status' => (string) $inventoryItem->status,
            'unit_of_measure' => (string) $inventoryItem->unit_of_measure,
            'quantity_on_hand' => (float) $inventoryItem->quantity_on_hand,
            'sales_price' => $inventoryItem->sales_price === null ? null : (float) $inventoryItem->sales_price,
            'cost_price' => $inventoryItem->cost_price === null ? null : (float) $inventoryItem->cost_price,
            'pricing_as_of' => $inventoryItem->pricing_as_of?->toDateString(),
            'stock_as_of' => $inventoryItem->stock_as_of?->toDateString(),
            'reorder_point' => $inventoryItem->reorder_point === null ? null : (int) $inventoryItem->reorder_point,
            'item_type' => (string) $inventoryItem->item_type,
            'category' => $inventoryItem->category,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload, bool $partial = false, ?string $currentItemType = null): array
    {
        $normalized = $payload;
        $itemType = (string) ($payload['item_type'] ?? ($currentItemType ?: 'inventory'));
        $isInventoryItem = $itemType === 'inventory';

        if (!$partial || array_key_exists('quantity_on_hand', $payload) || array_key_exists('item_type', $payload)) {
            $normalized['quantity_on_hand'] = $isInventoryItem
                ? (float) ($payload['quantity_on_hand'] ?? 0)
                : 0.0;
        }

        if (!$partial || array_key_exists('reorder_point', $payload) || array_key_exists('item_type', $payload)) {
            $normalized['reorder_point'] = $isInventoryItem
                ? (array_key_exists('reorder_point', $payload) && $payload['reorder_point'] !== null ? (int) $payload['reorder_point'] : null)
                : null;
        }

        if (!$partial || array_key_exists('sales_price', $payload)) {
            $normalized['sales_price'] = array_key_exists('sales_price', $payload) && $payload['sales_price'] !== null
                ? (float) $payload['sales_price']
                : null;
        }

        if (!$partial || array_key_exists('cost_price', $payload)) {
            $normalized['cost_price'] = array_key_exists('cost_price', $payload) && $payload['cost_price'] !== null
                ? (float) $payload['cost_price']
                : null;
        }

        if (!$partial || array_key_exists('pricing_as_of', $payload)) {
            $normalized['pricing_as_of'] = array_key_exists('pricing_as_of', $payload) && $payload['pricing_as_of'] !== null
                ? (string) $payload['pricing_as_of']
                : null;
        }

        if (!$partial || array_key_exists('stock_as_of', $payload)) {
            $normalized['stock_as_of'] = array_key_exists('stock_as_of', $payload) && $payload['stock_as_of'] !== null
                ? (string) $payload['stock_as_of']
                : null;
        }

        return $normalized;
    }
}

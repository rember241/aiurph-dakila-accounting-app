<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoiceLine;

/**
 * Applies inventory-side stock effects and movement ledger entries.
 *
 * This service does not open its own transaction. Callers are expected to
 * execute it inside the enclosing document transaction.
 */
final class InventoryMovementCommandService
{
    /**
     * Applies stock effects for an issued sales invoice.
     *
     * Only invoice lines snapshotted as `inventory` reduce stock. Service and
     * non-inventory lines are intentionally ignored here because they have no
     * quantity-on-hand ledger effect.
     *
     * @return array<string, int|float>
     */
    public function issueSalesInvoice(SalesInvoice $salesInvoice): array
    {
        $salesInvoice->loadMissing(['lines']);

        if ($salesInvoice->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Sales invoices must contain at least one line before issuing.',
            ]);
        }

        $inventoryLines = $salesInvoice->lines
            ->filter(static fn (SalesInvoiceLine $line): bool => (string) $line->item_type_snapshot === 'inventory')
            ->sortBy('line_number')
            ->values();

        if ($inventoryLines->isEmpty()) {
            return [
                'movement_count' => 0,
                'stock_line_count' => 0,
                'quantity_total' => 0.0,
            ];
        }

        $companyId = (string) $salesInvoice->company_id;
        $occurredAt = now();
        $stockAsOf = $occurredAt->toDateString();
        $movementDate = $occurredAt->toDateString();
        $inventoryItems = $this->loadInventoryItemsForPosting($companyId, $inventoryLines);

        $movementCount = 0;
        $quantityTotal = 0.0;

        foreach ($inventoryLines as $line) {
            $lineIndex = max(0, ((int) $line->line_number) - 1);
            $inventoryItem = $inventoryItems->get((string) $line->inventory_item_id);

            if (!$inventoryItem instanceof InventoryItem) {
                throw ValidationException::withMessages([
                    "lines.{$lineIndex}.inventory_item_id" => 'The stock item linked to this invoice line is no longer available.',
                ]);
            }

            if ((string) $inventoryItem->status !== 'active') {
                throw ValidationException::withMessages([
                    "lines.{$lineIndex}.inventory_item_id" => 'The stock item linked to this invoice line is inactive and cannot be issued.',
                ]);
            }

            if (!$inventoryItem->isStockTracked()) {
                throw ValidationException::withMessages([
                    "lines.{$lineIndex}.inventory_item_id" => 'The stock item linked to this invoice line is no longer stock-tracked.',
                ]);
            }

            $quantity = round((float) $line->quantity, 2);
            $quantityBefore = round((float) $inventoryItem->quantity_on_hand, 2);

            if ($quantityBefore < $quantity) {
                throw ValidationException::withMessages([
                    "lines.{$lineIndex}.quantity" => sprintf(
                        'Insufficient stock for %s. Available: %s. Required: %s.',
                        (string) $line->item_name_snapshot,
                        number_format($quantityBefore, 2, '.', ''),
                        number_format($quantity, 2, '.', '')
                    ),
                ]);
            }

            $quantityAfter = round($quantityBefore - $quantity, 2);
            $unitCost = $line->unit_cost_snapshot !== null
                ? (float) $line->unit_cost_snapshot
                : ($inventoryItem->cost_price === null ? null : (float) $inventoryItem->cost_price);

            $inventoryItem->forceFill([
                'quantity_on_hand' => $quantityAfter,
                'stock_as_of' => $stockAsOf,
            ])->save();

            InventoryMovement::query()->create([
                'company_id' => $companyId,
                'inventory_item_id' => (string) $inventoryItem->getKey(),
                'org_unit_id' => null,
                'sales_invoice_id' => (string) $salesInvoice->getKey(),
                'sales_invoice_line_id' => (string) $line->getKey(),
                'source_type' => 'sales_invoice',
                'source_id' => (string) $salesInvoice->getKey(),
                'movement_type' => 'sales_invoice_issue',
                'direction' => 'out',
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $unitCost,
                'movement_date' => $movementDate,
                'reference_number' => (string) $salesInvoice->invoice_number,
                'notes' => sprintf('Issued from sales invoice %s.', (string) $salesInvoice->invoice_number),
                'occurred_at' => $occurredAt,
            ]);

            $movementCount++;
            $quantityTotal = round($quantityTotal + $quantity, 2);
        }

        return [
            'movement_count' => $movementCount,
            'stock_line_count' => $inventoryLines->count(),
            'quantity_total' => $quantityTotal,
        ];
    }

    /**
     * Compatibility alias for older callers still using posting terminology.
     *
     * @return array<string, int|float>
     */
    public function postSalesInvoice(SalesInvoice $salesInvoice): array
    {
        return $this->issueSalesInvoice($salesInvoice);
    }

    /**
     * Restores stock effects for a cancelled sales invoice.
     *
     * This creates an equal and opposite inventory movement instead of editing
     * the original issue movement, preserving the stock ledger audit trail.
     *
     * @return array<string, int|float>
     */
    public function cancelSalesInvoice(SalesInvoice $salesInvoice): array
    {
        $salesInvoice->loadMissing(['lines']);

        $inventoryLines = $salesInvoice->lines
            ->filter(static fn (SalesInvoiceLine $line): bool => (string) $line->item_type_snapshot === 'inventory')
            ->sortBy('line_number')
            ->values();

        if ($inventoryLines->isEmpty()) {
            return [
                'movement_count' => 0,
                'stock_line_count' => 0,
                'quantity_total' => 0.0,
            ];
        }

        $companyId = (string) $salesInvoice->company_id;
        $occurredAt = now();
        $stockAsOf = $occurredAt->toDateString();
        $movementDate = $occurredAt->toDateString();
        $inventoryItems = $this->loadInventoryItemsForPosting($companyId, $inventoryLines);

        $movementCount = 0;
        $quantityTotal = 0.0;

        foreach ($inventoryLines as $line) {
            $lineIndex = max(0, ((int) $line->line_number) - 1);
            $inventoryItem = $inventoryItems->get((string) $line->inventory_item_id);

            if (!$inventoryItem instanceof InventoryItem) {
                throw ValidationException::withMessages([
                    "lines.{$lineIndex}.inventory_item_id" => 'The stock item linked to this invoice line is no longer available.',
                ]);
            }

            $quantity = round((float) $line->quantity, 2);
            $quantityBefore = round((float) $inventoryItem->quantity_on_hand, 2);
            $quantityAfter = round($quantityBefore + $quantity, 2);
            $unitCost = $line->unit_cost_snapshot !== null
                ? (float) $line->unit_cost_snapshot
                : ($inventoryItem->cost_price === null ? null : (float) $inventoryItem->cost_price);

            $inventoryItem->forceFill([
                'quantity_on_hand' => $quantityAfter,
                'stock_as_of' => $stockAsOf,
            ])->save();

            InventoryMovement::query()->create([
                'company_id' => $companyId,
                'inventory_item_id' => (string) $inventoryItem->getKey(),
                'org_unit_id' => null,
                'sales_invoice_id' => (string) $salesInvoice->getKey(),
                'sales_invoice_line_id' => (string) $line->getKey(),
                'source_type' => 'sales_invoice',
                'source_id' => (string) $salesInvoice->getKey(),
                'movement_type' => 'sales_invoice_cancel',
                'direction' => 'in',
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $unitCost,
                'movement_date' => $movementDate,
                'reference_number' => (string) $salesInvoice->invoice_number,
                'notes' => sprintf('Restored from cancelled sales invoice %s.', (string) $salesInvoice->invoice_number),
                'occurred_at' => $occurredAt,
            ]);

            $movementCount++;
            $quantityTotal = round($quantityTotal + $quantity, 2);
        }

        return [
            'movement_count' => $movementCount,
            'stock_line_count' => $inventoryLines->count(),
            'quantity_total' => $quantityTotal,
        ];
    }

    /**
     * Locks the live item rows before stock is checked and reduced.
     *
     * The caller owns the outer transaction, so this lock keeps simultaneous
     * invoice issues from overselling the same item.
     *
     * @param Collection<int, SalesInvoiceLine> $inventoryLines
     * @return Collection<string, InventoryItem>
     */
    private function loadInventoryItemsForPosting(string $companyId, Collection $inventoryLines): Collection
    {
        $inventoryItemIds = $inventoryLines
            ->map(static fn (SalesInvoiceLine $line): string => (string) $line->inventory_item_id)
            ->unique()
            ->values()
            ->all();

        return InventoryItem::query()
            ->forCompany($companyId)
            ->whereIn('id', $inventoryItemIds)
            ->lockForUpdate()
            ->get()
            ->keyBy(static fn (InventoryItem $inventoryItem): string => (string) $inventoryItem->getKey());
    }
}

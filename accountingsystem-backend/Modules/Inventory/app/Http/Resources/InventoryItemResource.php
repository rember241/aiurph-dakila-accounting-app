<?php

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Inventory item API resource.
 *
 * @mixin \Modules\Inventory\Models\InventoryItem
 */
final class InventoryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'company_id' => (string) $this->company_id,
            'tenant_id' => (string) $this->company_id,
            'code' => (string) $this->code,
            'sku' => (string) $this->code,
            'name' => (string) $this->name,
            'description' => $this->description,
            'status' => (string) $this->status,
            'is_active' => (string) $this->status === 'active',
            'unit_of_measure' => (string) $this->unit_of_measure,
            'quantity_on_hand' => (float) $this->quantity_on_hand,
            'sales_price' => $this->sales_price === null ? null : (float) $this->sales_price,
            'cost_price' => $this->cost_price === null ? null : (float) $this->cost_price,
            'stock_value' => $this->stockValue(),
            'pricing_as_of' => $this->pricing_as_of?->toDateString(),
            'stock_as_of' => $this->stock_as_of?->toDateString(),
            'reorder_point' => $this->reorder_point === null ? null : (int) $this->reorder_point,
            'is_low_stock' => $this->isLowStock(),
            'stock_health' => $this->stockHealth(),
            'item_type' => (string) $this->item_type,
            'category' => $this->category,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

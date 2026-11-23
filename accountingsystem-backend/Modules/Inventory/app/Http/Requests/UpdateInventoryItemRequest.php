<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Enums\InventoryItemStatus;
use Modules\Inventory\Enums\InventoryItemType;

/**
 * Validates inventory item update payloads.
 */
final class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->exists('code')) {
            $normalized['code'] = is_string($this->input('code'))
                ? strtoupper(trim($this->input('code')))
                : $this->input('code');
        }

        if ($this->exists('name')) {
            $normalized['name'] = is_string($this->input('name'))
                ? trim($this->input('name'))
                : $this->input('name');
        }

        if ($this->exists('description')) {
            $normalized['description'] = is_string($this->input('description'))
                ? trim($this->input('description'))
                : $this->input('description');
        }

        if ($this->exists('status')) {
            $normalized['status'] = is_string($this->input('status'))
                ? strtolower(trim($this->input('status')))
                : $this->input('status');
        }

        if ($this->exists('unit_of_measure')) {
            $normalized['unit_of_measure'] = is_string($this->input('unit_of_measure'))
                ? strtoupper(trim($this->input('unit_of_measure')))
                : $this->input('unit_of_measure');
        }

        if ($this->exists('quantity_on_hand')) {
            $normalized['quantity_on_hand'] = is_string($this->input('quantity_on_hand'))
                ? trim($this->input('quantity_on_hand'))
                : $this->input('quantity_on_hand');
        }

        if ($this->exists('sales_price')) {
            $normalized['sales_price'] = is_string($this->input('sales_price'))
                ? trim($this->input('sales_price'))
                : $this->input('sales_price');
        }

        if ($this->exists('cost_price')) {
            $normalized['cost_price'] = is_string($this->input('cost_price'))
                ? trim($this->input('cost_price'))
                : $this->input('cost_price');
        }

        if ($this->exists('pricing_as_of')) {
            $normalized['pricing_as_of'] = is_string($this->input('pricing_as_of'))
                ? trim($this->input('pricing_as_of'))
                : $this->input('pricing_as_of');
        }

        if ($this->exists('stock_as_of')) {
            $normalized['stock_as_of'] = is_string($this->input('stock_as_of'))
                ? trim($this->input('stock_as_of'))
                : $this->input('stock_as_of');
        }

        if ($this->exists('reorder_point')) {
            $normalized['reorder_point'] = is_string($this->input('reorder_point'))
                ? trim($this->input('reorder_point'))
                : $this->input('reorder_point');
        }

        if ($this->exists('item_type')) {
            $normalized['item_type'] = is_string($this->input('item_type'))
                ? strtolower(trim($this->input('item_type')))
                : $this->input('item_type');
        }

        if ($this->exists('category')) {
            $normalized['category'] = is_string($this->input('category'))
                ? trim($this->input('category'))
                : $this->input('category');
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = (string) $this->attributes->get('company_id', $this->attributes->get('tenant_id', ''));
        $inventoryRouteParam = $this->route('inventory');
        $inventoryId = is_object($inventoryRouteParam) && method_exists($inventoryRouteParam, 'getKey')
            ? (string) $inventoryRouteParam->getKey()
            : (string) $inventoryRouteParam;

        return [
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'code' => [
                'sometimes',
                'string',
                'max:64',
                'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('inventory_items', 'code')
                    ->ignore($inventoryId)
                    ->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', Rule::in(InventoryItemStatus::values())],
            'unit_of_measure' => ['sometimes', 'string', 'max:24'],
            'quantity_on_hand' => ['sometimes', 'numeric', 'min:0', 'max:999999999.99'],
            'sales_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'pricing_as_of' => ['nullable', 'date'],
            'stock_as_of' => ['nullable', 'date'],
            'reorder_point' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'item_type' => ['sometimes', 'string', Rule::in(InventoryItemType::values())],
            'category' => ['nullable', 'string', 'max:80'],
        ];
    }
}

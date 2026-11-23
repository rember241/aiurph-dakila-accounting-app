<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Enums\InventoryItemStatus;
use Modules\Inventory\Enums\InventoryItemType;

/**
 * Validates inventory item create payloads.
 */
final class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code')) ? strtoupper(trim($this->input('code'))) : $this->input('code'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
            'status' => is_string($this->input('status')) ? strtolower(trim($this->input('status'))) : $this->input('status'),
            'unit_of_measure' => is_string($this->input('unit_of_measure')) ? strtoupper(trim($this->input('unit_of_measure'))) : $this->input('unit_of_measure'),
            'quantity_on_hand' => is_string($this->input('quantity_on_hand')) ? trim($this->input('quantity_on_hand')) : $this->input('quantity_on_hand'),
            'sales_price' => is_string($this->input('sales_price')) ? trim($this->input('sales_price')) : $this->input('sales_price'),
            'cost_price' => is_string($this->input('cost_price')) ? trim($this->input('cost_price')) : $this->input('cost_price'),
            'pricing_as_of' => is_string($this->input('pricing_as_of')) ? trim($this->input('pricing_as_of')) : $this->input('pricing_as_of'),
            'stock_as_of' => is_string($this->input('stock_as_of')) ? trim($this->input('stock_as_of')) : $this->input('stock_as_of'),
            'reorder_point' => is_string($this->input('reorder_point')) ? trim($this->input('reorder_point')) : $this->input('reorder_point'),
            'item_type' => is_string($this->input('item_type')) ? strtolower(trim($this->input('item_type'))) : $this->input('item_type'),
            'category' => is_string($this->input('category')) ? trim($this->input('category')) : $this->input('category'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = (string) $this->attributes->get('company_id', $this->attributes->get('tenant_id', ''));

        return [
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('inventory_items', 'code')->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', Rule::in(InventoryItemStatus::values())],
            'unit_of_measure' => ['required', 'string', 'max:24'],
            'quantity_on_hand' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'sales_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'pricing_as_of' => ['nullable', 'date'],
            'stock_as_of' => ['nullable', 'date'],
            'reorder_point' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'item_type' => ['required', 'string', Rule::in(InventoryItemType::values())],
            'category' => ['nullable', 'string', 'max:80'],
        ];
    }
}

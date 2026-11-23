<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Enums\InventoryItemStatus;
use Modules\Inventory\Enums\InventoryItemType;

/**
 * Validates inventory item listing filters.
 */
final class IndexInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => is_string($this->input('q')) ? trim($this->input('q')) : $this->input('q'),
            'status' => is_string($this->input('status')) ? strtolower(trim($this->input('status'))) : $this->input('status'),
            'item_type' => is_string($this->input('item_type')) ? strtolower(trim($this->input('item_type'))) : $this->input('item_type'),
            'category' => is_string($this->input('category')) ? trim($this->input('category')) : $this->input('category'),
            'sortField' => is_string($this->input('sortField')) ? trim($this->input('sortField')) : $this->input('sortField'),
            'sortDir' => is_string($this->input('sortDir')) ? strtolower(trim($this->input('sortDir'))) : $this->input('sortDir'),
            'perPage' => $this->input('perPage'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(InventoryItemStatus::values())],
            'item_type' => ['nullable', 'string', Rule::in(InventoryItemType::values())],
            'category' => ['nullable', 'string', 'max:80'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortField' => ['nullable', 'string', Rule::in(['code', 'name', 'status', 'item_type', 'unit_of_measure', 'quantity_on_hand', 'sales_price', 'cost_price', 'reorder_point', 'created_at', 'updated_at'])],
            'sortDir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}

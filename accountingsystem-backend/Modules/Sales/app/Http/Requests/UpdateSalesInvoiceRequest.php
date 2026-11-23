<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates sales invoice draft update payloads.
 */
final class UpdateSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        $this->merge([
            'invoice_date' => is_string($this->input('invoice_date')) ? trim($this->input('invoice_date')) : $this->input('invoice_date'),
            'due_date' => is_string($this->input('due_date')) ? trim($this->input('due_date')) : $this->input('due_date'),
            'customer_id' => is_string($this->input('customer_id')) ? trim($this->input('customer_id')) : $this->input('customer_id'),
            'notes' => is_string($this->input('notes')) ? trim($this->input('notes')) : $this->input('notes'),
            'lines' => is_array($lines)
                ? array_map(static function ($line) {
                    if (!is_array($line)) {
                        return $line;
                    }

                    return [
                        'inventory_item_id' => is_string($line['inventory_item_id'] ?? null) ? trim((string) $line['inventory_item_id']) : ($line['inventory_item_id'] ?? null),
                        'tax_id' => is_string($line['tax_id'] ?? null) ? trim((string) $line['tax_id']) : ($line['tax_id'] ?? null),
                        'quantity' => is_string($line['quantity'] ?? null) ? trim((string) $line['quantity']) : ($line['quantity'] ?? null),
                    ];
                }, $lines)
                : $lines,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'invoice_number' => ['prohibited'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'customer_id' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.inventory_item_id' => ['required', 'uuid', 'distinct'],
            'lines.*.tax_id' => ['nullable', 'uuid'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
        ];
    }
}

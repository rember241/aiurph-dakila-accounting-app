<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Sales\Enums\SalesInvoiceStatus;

/**
 * Validates sales invoice draft list filters.
 */
final class IndexSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['q', 'status', 'customer_id', 'sortField', 'sortDir'] as $key) {
            if ($this->exists($key)) {
                $value = $this->input($key);
                $normalized[$key] = is_string($value) ? trim($value) : $value;
            }
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
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:160'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(SalesInvoiceStatus::values())],
            'customer_id' => ['sometimes', 'nullable', 'uuid'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:100'],
            'sortField' => ['sometimes', 'nullable', 'string', Rule::in(['invoice_number', 'invoice_date', 'subtotal', 'created_at', 'updated_at'])],
            'sortDir' => ['sometimes', 'nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}

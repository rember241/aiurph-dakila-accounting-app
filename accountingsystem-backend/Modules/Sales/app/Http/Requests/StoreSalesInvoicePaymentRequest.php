<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSalesInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_date' => is_string($this->input('payment_date')) ? trim($this->input('payment_date')) : $this->input('payment_date'),
            'amount' => is_string($this->input('amount')) ? trim($this->input('amount')) : $this->input('amount'),
            'receipt_account_id' => $this->normalizeOptionalUuid($this->input('receipt_account_id')),
            'reference_no' => $this->normalizeOptionalString($this->input('reference_no')),
            'payment_method' => $this->normalizeOptionalString($this->input('payment_method')),
            'payment_channel' => $this->normalizeOptionalString($this->input('payment_channel')),
            'notes' => $this->normalizeOptionalString($this->input('notes')),
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
            'sales_invoice_id' => ['prohibited'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'receipt_account_id' => ['nullable', 'uuid'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'payment_method' => ['required', 'string', 'in:cash,check,bank_transfer,online'],
            'payment_channel' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function normalizeOptionalString(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeOptionalUuid(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}

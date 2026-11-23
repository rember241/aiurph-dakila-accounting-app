<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IndexSalesPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => is_string($this->input('search')) ? trim($this->input('search')) : null,
            'status' => is_string($this->input('status')) ? trim($this->input('status')) : null,
            'customer_id' => is_string($this->input('customer_id')) && trim($this->input('customer_id')) !== ''
                ? trim($this->input('customer_id'))
                : null,
            'sales_invoice_id' => is_string($this->input('sales_invoice_id')) && trim($this->input('sales_invoice_id')) !== ''
                ? trim($this->input('sales_invoice_id'))
                : null,
            'payment_method' => is_string($this->input('payment_method')) && trim($this->input('payment_method')) !== ''
                ? trim($this->input('payment_method'))
                : null,
            'date_from' => is_string($this->input('date_from')) && trim($this->input('date_from')) !== ''
                ? trim($this->input('date_from'))
                : null,
            'date_to' => is_string($this->input('date_to')) && trim($this->input('date_to')) !== ''
                ? trim($this->input('date_to'))
                : null,
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
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:posted,voided,unposted'],
            'customer_id' => ['nullable', 'uuid'],
            'sales_invoice_id' => ['nullable', 'uuid'],
            'payment_method' => ['nullable', 'string', 'in:cash,check,bank_transfer,online'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}

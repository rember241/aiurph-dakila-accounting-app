<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VoidSalesInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => $this->normalizeOptionalString($this->input('reason')),
            'posting_date' => is_string($this->input('posting_date')) && trim($this->input('posting_date')) !== ''
                ? trim($this->input('posting_date'))
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
            'reason' => ['nullable', 'string', 'max:500'],
            'posting_date' => ['nullable', 'date'],
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
}

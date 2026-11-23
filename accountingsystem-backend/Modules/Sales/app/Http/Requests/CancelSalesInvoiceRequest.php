<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates sales invoice cancellation requests.
 */
final class CancelSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cancelled_reason' => is_string($this->input('cancelled_reason'))
                ? trim($this->input('cancelled_reason'))
                : $this->input('cancelled_reason'),
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
            'cancelled_at' => ['prohibited'],
            'cancelled_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

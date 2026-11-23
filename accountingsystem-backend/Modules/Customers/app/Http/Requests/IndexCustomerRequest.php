<?php

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerStatus;

/**
 * Validates customer listing filters.
 */
final class IndexCustomerRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in(CustomerStatus::values())],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortField' => ['nullable', 'string', Rule::in(['code', 'name', 'email', 'phone', 'status', 'created_at', 'updated_at'])],
            'sortDir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}

<?php

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerGroup;
use Modules\Customers\Enums\CustomerStatus;

/**
 * Validates customer create payloads.
 */
final class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $customerGroup = $this->input('customer_group');

        if (is_string($customerGroup)) {
            $customerGroup = strtolower(trim($customerGroup));
            $customerGroup = $customerGroup === '' ? null : $customerGroup;
        }

        $this->merge([
            'code' => is_string($this->input('code'))
                ? (($value = strtoupper(trim($this->input('code')))) === '' ? null : $value)
                : $this->input('code'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'customer_group' => $customerGroup,
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'phone' => is_string($this->input('phone')) ? trim($this->input('phone')) : $this->input('phone'),
            'address' => is_string($this->input('address')) ? trim($this->input('address')) : $this->input('address'),
            'status' => is_string($this->input('status')) ? strtolower(trim($this->input('status'))) : $this->input('status'),
            'latitude' => $this->input('latitude') !== null ? (float) $this->input('latitude') : null,
            'longitude' => $this->input('longitude') !== null ? (float) $this->input('longitude') : null,
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
                'nullable',
                'string',
                'max:40',
                'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('customers', 'code')->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:160'],
            'customer_group' => ['nullable', 'string', Rule::in(CustomerGroup::values())],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['nullable', 'string', Rule::in(CustomerStatus::values())],
        ];
    }
}

<?php

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerGroup;
use Modules\Customers\Enums\CustomerStatus;

/**
 * Validates customer update payloads.
 */
final class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->exists('code')) {
            $normalized['code'] = is_string($this->input('code'))
                ? strtoupper(trim($this->input('code')))
                : $this->input('code');
        }

        if ($this->exists('name')) {
            $normalized['name'] = is_string($this->input('name'))
                ? trim($this->input('name'))
                : $this->input('name');
        }

        if ($this->exists('customer_group')) {
            $normalized['customer_group'] = is_string($this->input('customer_group'))
                ? (($value = strtolower(trim($this->input('customer_group')))) === '' ? null : $value)
                : $this->input('customer_group');
        }

        if ($this->exists('email')) {
            $normalized['email'] = is_string($this->input('email'))
                ? mb_strtolower(trim($this->input('email')))
                : $this->input('email');
        }

        if ($this->exists('phone')) {
            $normalized['phone'] = is_string($this->input('phone'))
                ? trim($this->input('phone'))
                : $this->input('phone');
        }

        if ($this->exists('address')) {
            $normalized['address'] = is_string($this->input('address'))
                ? trim($this->input('address'))
                : $this->input('address');
        }

        if ($this->exists('status')) {
            $normalized['status'] = is_string($this->input('status'))
                ? strtolower(trim($this->input('status')))
                : $this->input('status');
        }

        if ($this->exists('latitude')) {
            $normalized['latitude'] = $this->input('latitude') !== null ? (float) $this->input('latitude') : null;
        }

        if ($this->exists('longitude')) {
            $normalized['longitude'] = $this->input('longitude') !== null ? (float) $this->input('longitude') : null;
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
        $companyId = (string) $this->attributes->get('company_id', $this->attributes->get('tenant_id', ''));
        $customerRouteParam = $this->route('customer');
        $customerId = is_object($customerRouteParam) && method_exists($customerRouteParam, 'getKey')
            ? (string) $customerRouteParam->getKey()
            : (string) $customerRouteParam;

        return [
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'code' => [
                'sometimes',
                'string',
                'max:40',
                'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('customers', 'code')
                    ->ignore($customerId)
                    ->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['sometimes', 'string', 'min:1', 'max:160'],
            'customer_group' => ['sometimes', 'nullable', 'string', Rule::in(CustomerGroup::values())],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['sometimes', 'string', Rule::in(CustomerStatus::values())],
        ];
    }
}

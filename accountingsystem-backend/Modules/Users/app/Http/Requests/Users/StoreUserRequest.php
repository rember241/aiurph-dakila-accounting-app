<?php

namespace Modules\Users\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Users\Enums\UserStatus;

/**
 * StoreUserRequest
 *
 * Handles validation for creating a new user.
 *
 * Step-by-step:
 * 1) Validate payload (name/email/password/roles).
 * 2) Command service creates user + assigns roles + audits the operation.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'password' => is_string($this->input('password')) ? trim($this->input('password')) : $this->input('password'),
            'department' => is_string($this->input('department')) ? trim($this->input('department')) : $this->input('department'),
            'avatar_url' => is_string($this->input('avatar_url')) ? trim($this->input('avatar_url')) : $this->input('avatar_url'),
        ]);
    }

    private function currentTenantId(): string
    {
        return (string) $this->attributes->get('company_id', $this->attributes->get('tenant_id', ''));
    }

    public function rules(): array
    {
        $tenantId = $this->currentTenantId();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:120'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(UserStatus::values())],
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'department' => ['nullable', 'string', 'max:120'],
            'avatar_url' => ['nullable', 'string', 'url', 'max:500'],
            'primary_org_unit_id' => [
                'nullable',
                'uuid',
                Rule::exists('org_units', 'id')->where(fn($query) => $query->where('company_id', $tenantId)),
            ],
            'org_unit_ids' => ['nullable', 'array'],
            'org_unit_ids.*' => [
                'uuid',
                Rule::exists('org_units', 'id')->where(fn($query) => $query->where('company_id', $tenantId)),
            ],
            'role_ids' => ['required', 'array', 'size:1'],
            'role_ids.*' => [
                'uuid',
                Rule::exists('roles', 'id')->where(fn($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }
}

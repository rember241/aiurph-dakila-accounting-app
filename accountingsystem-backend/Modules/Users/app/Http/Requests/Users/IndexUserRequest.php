<?php

namespace Modules\Users\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Users\Enums\UserStatus;

/**
 * IndexUserRequest
 *
 * Validates list filters for the Users index endpoint.
 */
class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function currentTenantId(): string
    {
        return (string) $this->attributes->get('company_id', $this->attributes->get('tenant_id', ''));
    }

    public function rules(): array
    {
        $tenantId = $this->currentTenantId();

        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(UserStatus::values())],
            'department' => ['nullable', 'string', 'max:120'],
            'org_unit_id' => [
                'nullable',
                'uuid',
                Rule::exists('org_units', 'id')->where(fn($query) => $query->where('company_id', $tenantId)),
            ],
            'role_id' => [
                'nullable',
                'uuid',
                Rule::exists('roles', 'id')->where(fn($query) => $query->where('tenant_id', $tenantId)),
            ],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortField' => ['nullable', 'string', Rule::in(['name', 'email', 'status', 'department', 'last_active_at', 'created_at'])],
            'sortDir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}

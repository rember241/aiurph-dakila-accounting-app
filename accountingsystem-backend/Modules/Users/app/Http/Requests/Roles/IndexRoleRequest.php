<?php

namespace Modules\Users\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Users\Enums\RoleStatus;

/**
 * IndexRoleRequest
 *
 * Validates list filters for the Roles index endpoint.
 */
class IndexRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'company_id' => ['nullable', 'uuid', Rule::exists('tenants', 'id')],
            'tenant_id' => ['nullable', 'uuid', Rule::exists('tenants', 'id')],
            'status' => ['nullable', 'string', Rule::in(RoleStatus::values())],
            'department' => ['nullable', 'string', 'max:120'],
            'org_unit_id' => ['nullable', 'uuid', Rule::exists('org_units', 'id')],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortField' => ['nullable', 'string', Rule::in(['name', 'key', 'status', 'department', 'updated_at', 'created_at'])],
            'sortDir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}

<?php

namespace Modules\Users\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Users\Enums\AccessLevel;
use Modules\Users\Enums\PermissionScope;
use Modules\Users\Enums\RoleStatus;
use Modules\Users\Rules\CompatibleAccessLevelModulePermissions;
use Modules\Users\Rules\ValidModulePermissions;

/**
 * UpdateRoleRequest
 *
 * Validates payload for updating an existing role.
 */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id;
        $companyId = $this->attributes->get('company_id')
            ?? $this->attributes->get('tenant_id')
            ?? $this->input('company_id')
            ?? $this->input('tenant_id', $this->route('role')?->tenant_id);
        $accessLevel = (string) $this->input('access_level', $this->route('role')?->access_level ?? AccessLevel::READ->value);
        $keyUniqueRule = Rule::unique('roles', 'key')
            ->ignore($roleId)
            ->where(function ($query) use ($companyId) {
                if ($companyId !== null && $companyId !== '') {
                    $query->where('tenant_id', $companyId);
                } else {
                    $query->whereNull('tenant_id');
                }
            });

        return [
            'company_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('tenants', 'id')],
            'tenant_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('tenants', 'id')],
            'key' => ['sometimes', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_-]*$/', $keyUniqueRule],
            'name' => ['sometimes', 'string', 'max:120'],
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'org_unit_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('org_units', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'status' => ['sometimes', 'string', Rule::in(RoleStatus::values())],
            'access_level' => ['sometimes', 'string', Rule::in(AccessLevel::values())],
            'permission_scope' => ['sometimes', 'string', Rule::in(PermissionScope::values())],
            'module_permissions' => [
                'sometimes',
                'nullable',
                new ValidModulePermissions(),
                new CompatibleAccessLevelModulePermissions($accessLevel),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}

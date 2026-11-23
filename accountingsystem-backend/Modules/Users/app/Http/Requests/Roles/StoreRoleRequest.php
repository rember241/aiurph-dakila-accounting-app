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
 * StoreRoleRequest
 *
 * Validates payload for creating a new role.
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->attributes->get('company_id')
            ?? $this->attributes->get('tenant_id')
            ?? $this->input('company_id')
            ?? $this->input('tenant_id');
        $accessLevel = (string) $this->input('access_level', AccessLevel::READ->value);
        $keyUniqueRule = Rule::unique('roles', 'key')->where(function ($query) use ($companyId) {
                if ($companyId !== null && $companyId !== '') {
                    $query->where('tenant_id', $companyId);
                } else {
                    $query->whereNull('tenant_id');
                }
            });

        return [
            'company_id' => ['nullable', 'uuid', Rule::exists('tenants', 'id')],
            'tenant_id' => ['nullable', 'uuid', Rule::exists('tenants', 'id')],
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_-]*$/', $keyUniqueRule],
            'name' => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'org_unit_id' => [
                'nullable',
                'uuid',
                Rule::exists('org_units', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'status' => ['required', 'string', Rule::in(RoleStatus::values())],
            'access_level' => ['required', 'string', Rule::in(AccessLevel::values())],
            'permission_scope' => ['required', 'string', Rule::in(PermissionScope::values())],
            'module_permissions' => [
                'nullable',
                new ValidModulePermissions(),
                new CompatibleAccessLevelModulePermissions($accessLevel),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

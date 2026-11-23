<?php

namespace Modules\Users\Http\Requests\OrgUnits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Users\Enums\OrgUnitStatus;
use Modules\Users\Enums\OrgUnitType;

/**
 * Validates organization unit update payloads.
 */
final class UpdateOrgUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->exists('name')) {
            $payload['name'] = is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name');
        }

        if ($this->exists('code')) {
            $payload['code'] = is_string($this->input('code')) ? strtoupper(trim($this->input('code'))) : $this->input('code');
        }

        if ($this->exists('type')) {
            $payload['type'] = is_string($this->input('type')) ? strtolower(trim($this->input('type'))) : $this->input('type');
        }

        if ($this->exists('status')) {
            $payload['status'] = is_string($this->input('status')) ? strtolower(trim($this->input('status'))) : $this->input('status');
        }

        if ($this->exists('location')) {
            $payload['location'] = is_string($this->input('location')) ? trim($this->input('location')) : $this->input('location');
        }

        if ($this->exists('description')) {
            $payload['description'] = is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description');
        }

        if ($this->exists('parent_org_unit_id')) {
            $payload['parent_org_unit_id'] = is_string($this->input('parent_org_unit_id')) && trim($this->input('parent_org_unit_id')) !== ''
                ? trim($this->input('parent_org_unit_id'))
                : $this->input('parent_org_unit_id');
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        $companyId = (string) $this->attributes->get('company_id', $this->attributes->get('tenant_id', ''));
        $orgUnitId = (string) ($this->route('orgUnit')?->getKey() ?? '');

        return [
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'is_root' => ['prohibited'],
            'name' => ['sometimes', 'string', 'max:160'],
            'code' => [
                'sometimes',
                'string',
                'max:24',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('org_units', 'code')
                    ->where(static fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($orgUnitId),
            ],
            'type' => ['sometimes', 'string', Rule::in(OrgUnitType::values())],
            'status' => ['sometimes', 'string', Rule::in(OrgUnitStatus::values())],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:240'],
            'parent_org_unit_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::notIn($orgUnitId !== '' ? [$orgUnitId] : []),
                Rule::exists('org_units', 'id')->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
        ];
    }
}

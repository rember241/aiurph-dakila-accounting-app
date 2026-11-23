<?php

namespace Modules\Users\Http\Requests\OrgUnits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Users\Enums\OrgUnitStatus;
use Modules\Users\Enums\OrgUnitType;

/**
 * Validates organization unit create payloads.
 */
final class StoreOrgUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'code' => is_string($this->input('code')) ? strtoupper(trim($this->input('code'))) : $this->input('code'),
            'type' => is_string($this->input('type')) ? strtolower(trim($this->input('type'))) : $this->input('type'),
            'status' => is_string($this->input('status')) ? strtolower(trim($this->input('status'))) : $this->input('status'),
            'location' => is_string($this->input('location')) ? trim($this->input('location')) : $this->input('location'),
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
            'parent_org_unit_id' => is_string($this->input('parent_org_unit_id')) && trim($this->input('parent_org_unit_id')) !== ''
                ? trim($this->input('parent_org_unit_id'))
                : null,
        ]);
    }

    public function rules(): array
    {
        $companyId = (string) $this->attributes->get('company_id', $this->attributes->get('tenant_id', ''));

        return [
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'is_root' => ['prohibited'],
            'name' => ['required', 'string', 'max:160'],
            'code' => [
                'required',
                'string',
                'max:24',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('org_units', 'code')->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
            'type' => ['required', 'string', Rule::in(OrgUnitType::values())],
            'status' => ['nullable', 'string', Rule::in(OrgUnitStatus::values())],
            'location' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:240'],
            'parent_org_unit_id' => [
                'nullable',
                'uuid',
                Rule::exists('org_units', 'id')->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
        ];
    }
}

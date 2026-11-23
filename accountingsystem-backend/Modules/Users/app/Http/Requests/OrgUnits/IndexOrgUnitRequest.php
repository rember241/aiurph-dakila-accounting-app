<?php

namespace Modules\Users\Http\Requests\OrgUnits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Users\Enums\OrgUnitStatus;
use Modules\Users\Enums\OrgUnitType;

/**
 * Validates list filters for organization unit listings.
 */
final class IndexOrgUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (string) $this->attributes->get('company_id', $this->attributes->get('tenant_id', ''));

        return [
            'q' => ['nullable', 'string', 'max:160'],
            'type' => ['nullable', 'string', Rule::in(OrgUnitType::values())],
            'status' => ['nullable', 'string', Rule::in(OrgUnitStatus::values())],
            'parent_org_unit_id' => [
                'nullable',
                'uuid',
                Rule::exists('org_units', 'id')->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
            'root_only' => ['nullable', 'boolean'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortField' => ['nullable', 'string', Rule::in(['name', 'type', 'status', 'created_at', 'updated_at'])],
            'sortDir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}

<?php

namespace Modules\Users\Http\Requests\OrgUnits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Users\Enums\OrgUnitStatus;

/**
 * Validates organization unit status transitions.
 */
final class TransitionOrgUnitStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => is_string($this->input('status')) ? strtolower(trim($this->input('status'))) : $this->input('status'),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(OrgUnitStatus::values())],
        ];
    }
}

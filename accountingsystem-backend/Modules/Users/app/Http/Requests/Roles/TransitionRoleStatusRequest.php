<?php

namespace Modules\Users\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Users\Enums\RoleStatus;

/**
 * TransitionRoleStatusRequest
 *
 * Validates role activation/deactivation operations.
 */
class TransitionRoleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(RoleStatus::values())],
        ];
    }
}


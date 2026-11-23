<?php

namespace Modules\Users\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Users\Enums\UserStatus;

/**
 * TransitionUserStatusRequest
 *
 * Validates user activation/deactivation operations.
 */
class TransitionUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(UserStatus::values())],
        ];
    }
}


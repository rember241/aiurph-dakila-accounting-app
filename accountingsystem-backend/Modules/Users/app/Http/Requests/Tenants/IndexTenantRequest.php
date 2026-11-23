<?php

namespace Modules\Users\Http\Requests\Tenants;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates list filters for the tenants index endpoint.
 */
class IndexTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortField' => ['nullable', 'string', Rule::in(['name', 'slug', 'status', 'timezone', 'created_at', 'updated_at'])],
            'sortDir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}

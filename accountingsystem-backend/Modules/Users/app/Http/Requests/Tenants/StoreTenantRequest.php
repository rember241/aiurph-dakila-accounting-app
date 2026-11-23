<?php

namespace Modules\Users\Http\Requests\Tenants;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates tenant bootstrap requests.
 *
 * The authenticated user becomes the owner automatically, so ownership and
 * membership-related fields are intentionally not accepted from the payload.
 */
class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'slug' => is_string($slug) && trim($slug) !== '' ? Str::slug($slug) : null,
            'timezone' => is_string($this->input('timezone')) ? trim($this->input('timezone')) : $this->input('timezone'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tenants', 'slug')],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'status' => ['prohibited'],
            'owner_user_id' => ['prohibited'],
            'database' => ['prohibited'],
            'database_name' => ['prohibited'],
            'membership' => ['prohibited'],
            'membership_roles' => ['prohibited'],
            'role_ids' => ['prohibited'],
            'invite' => ['prohibited'],
            'invites' => ['prohibited'],
        ];
    }
}

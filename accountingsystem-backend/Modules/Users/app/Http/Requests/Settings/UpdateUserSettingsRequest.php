<?php

namespace Modules\Users\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $email = $this->input('email');
        $phone = $this->input('phone');
        $currencyCode = $this->input('currency_code');

        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'email' => is_string($email) ? mb_strtolower(trim($email)) : $email,
            'phone' => is_string($phone) ? $this->normalizeOptionalString($phone) : $phone,
            'currency_code' => is_string($currencyCode) ? strtoupper(trim($currencyCode)) : $currencyCode,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:120',
                Rule::unique('users', 'email')->ignore($user?->getAuthIdentifier()),
            ],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\.\s]+$/'],
            'currency_code' => ['required', 'string', Rule::in(['PHP', 'USD', 'EUR', 'SGD', 'GBP', 'JPY', 'AUD'])],
        ];
    }

    private function normalizeOptionalString(string $value): ?string
    {
        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}

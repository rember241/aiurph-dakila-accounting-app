<?php

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates query parameters for the customer account statement endpoint.
 *
 * Both dates are required and must be supplied in ISO date format.
 * The end date must fall on or after the start date.
 */
final class CustomerBillingStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['date_from', 'date_to'] as $key) {
            if ($this->exists($key)) {
                $value = $this->input($key);
                $normalized[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to'   => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.required'     => 'A start date is required.',
            'date_from.date_format'  => 'The start date must be in YYYY-MM-DD format.',
            'date_to.required'       => 'An end date is required.',
            'date_to.date_format'    => 'The end date must be in YYYY-MM-DD format.',
            'date_to.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}

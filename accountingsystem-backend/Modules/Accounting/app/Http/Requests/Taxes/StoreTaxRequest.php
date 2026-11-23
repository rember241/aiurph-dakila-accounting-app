<?php

namespace Modules\Accounting\Http\Requests\Taxes;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'rate' => ['required', 'numeric', 'between:0.01,100.00', 'decimal:0,2'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // TODO: Change when authentication is in place
    }
}

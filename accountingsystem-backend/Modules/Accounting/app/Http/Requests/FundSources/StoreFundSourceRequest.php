<?php

namespace Modules\Accounting\Http\Requests\FundSources;

use Illuminate\Foundation\Http\FormRequest;

class StoreFundSourceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // TODO: Change when authentication is implemented
    }
}

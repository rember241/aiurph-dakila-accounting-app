<?php

namespace Modules\Accounting\Http\Requests\FundSources;

use Illuminate\Foundation\Http\FormRequest;

class IndexFundSourceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'search' =>       ['nullable'],
            'is_paginated' => ['nullable', 'boolean'],
            'sort' =>         ['nullable', 'in:name'],
            'order' =>        ['nullable', 'in:asc,desc'],
            'page' =>         ['nullable', 'integer', 'min:1'],
            'status' =>       ['nullable', 'in:active,inactive'],
            'per_page' => [
                'nullable',
                'integer',
                'min:5',
                'max:50',
                'in:5,10,20,30,50'
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // TODO: Change when authentication is implemented
    }

    /**
     * Cast the value of the is_paginated request parameter
     * to a proper boolean before validation
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('is_paginated')) {
            $this->merge([
                'is_paginated' => filter_var($this->is_paginated, FILTER_VALIDATE_BOOLEAN)
            ]);
        }
    }
}

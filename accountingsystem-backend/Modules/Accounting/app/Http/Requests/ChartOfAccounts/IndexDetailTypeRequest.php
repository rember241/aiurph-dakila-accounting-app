<?php

namespace Modules\Accounting\Http\Requests\ChartOfAccounts;

use Illuminate\Foundation\Http\FormRequest;

class IndexDetailTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accountType' => ['required', 'integer', 'exists:account_types,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'accountType' => $this->route('accountType'),
        ]);
    }
}

<?php

namespace Modules\Accounting\Http\Requests\ChartOfAccounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAccountTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'core_type' => ['nullable', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
        ];
    }
}

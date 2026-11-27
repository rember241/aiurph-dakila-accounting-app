<?php

namespace Modules\Accounting\Http\Requests\ChartOfAccounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Enums\AccountCategory;


/**
 * IndexAccountRequest
 *
 * Handles validation for retrieving a list of Chart of Accounts (COA) records.
 *
 * This request is intentionally permissive to allow for flexible querying.
 */
class IndexAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', Rule::enum(AccountCategory::class)],
            'status' => ['nullable', 'in:active,inactive'],
            'is_posting' => ['nullable', 'in:t,f,true,false,1,0'],
            'nested' => ['nullable', 'in:t,f,true,false,1,0'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sortField' => ['nullable', 'in:code,name,type,status,is_posting,normal_balance,account_type_name,detail_type_name,created_at'],
            'sortDir' => ['nullable', 'in:asc,desc'],
        ];
    }

}

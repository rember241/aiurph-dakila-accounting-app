<?php

namespace Modules\Accounting\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountingDefaultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accounts_receivable_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('accounts', 'id')->where('type', 'asset')->where('is_posting', true),
            ],
            'sales_income_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('accounts', 'id')->where('type', 'revenue')->where('is_posting', true),
            ],
            'inventory_asset_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('accounts', 'id')->where('type', 'asset')->where('is_posting', true),
            ],
            'cogs_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('accounts', 'id')->where('type', 'expense')->where('is_posting', true),
            ],
            'sales_tax_payable_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('accounts', 'id')->where('type', 'liability')->where('is_posting', true),
            ],
        ];
    }
}

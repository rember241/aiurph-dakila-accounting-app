<?php

namespace Modules\Accounting\Http\Requests\GeneralLedger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FetchAccountsRequest extends FormRequest
{
    private function companyId(): string
    {
        return (string) ($this->attributes->get('company_id') ?? $this->attributes->get('tenant_id', ''));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        return [
            'startDate' =>  ['required', 'date_format:Y-m-d', 'before_or_equal:endDate'],
            'endDate'   =>  ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'accountIds' => ['required', 'array', 'min:1'],

            'accountIds.*' => [
                'required',
                'uuid',
                Rule::exists('accounts', 'id')->where(function ($query) use ($companyId) {
                    $query->where('is_posting', true)
                        ->where('company_id', $companyId);
                }),
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}

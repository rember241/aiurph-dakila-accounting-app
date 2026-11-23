<?php

namespace Modules\Accounting\Http\Requests\IncomeStatement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DownloadIncomeStatementRequest extends FormRequest
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
            'fileType' =>   ['required', 'in:pdf,xlsx,csv'],
            'startDate' =>  ['required', 'date_format:Y-m-d', 'before_or_equal:endDate'],
            'endDate'   =>  ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'compareWith' => ['nullable', 'in:period,fiscalYear,specific'],
            'numberOfPeriods' => ['nullable', 'integer', 'min:1', 'max:36'],
            'compareStartDate' =>  ['nullable', 'date_format:Y-m-d', 'before:startDate', 'before_or_equal:compareEndDate'],
            'compareEndDate'   =>  ['nullable', 'date_format:Y-m-d', 'before:startDate', 'after_or_equal:compareStartDate'],
            'accountIds' => ['required', 'array', 'min:2'],

            'accountIds.*' => [
                'required',
                'uuid',
                Rule::exists('accounts', 'id')->where(function ($query) use ($companyId) {
                    $query->where('is_posting', true)
                        ->where('company_id', $companyId)
                        ->whereIn('type', ['revenue', 'expense']);
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

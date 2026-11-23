<?php

namespace Modules\Accounting\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCashFlowRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'startDate' =>  ['required', 'date_format:Y-m-d', 'before_or_equal:endDate'],
            'endDate'   =>  ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'compareEnabled' => ['sometimes', 'in:true,false,1,0'],
            'comparisonMode' => ['required_if:compareEnabled,true,1', 'nullable', 'in:previous_period,previous_year,custom'],
            'comparisonStartDate' => [
                'required_if:comparisonMode,custom',
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:comparisonEndDate',
            ],
            'comparisonEndDate' => [
                'required_if:comparisonMode,custom',
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:comparisonStartDate',
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

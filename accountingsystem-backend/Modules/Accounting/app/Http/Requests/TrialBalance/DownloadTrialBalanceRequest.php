<?php

namespace Modules\Accounting\Http\Requests\TrialBalance;

use Illuminate\Foundation\Http\FormRequest;

class DownloadTrialBalanceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'fileType' => ['required', 'in:csv,pdf,xlsx'],
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

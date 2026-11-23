<?php

namespace Modules\Accounting\Http\Requests\JournalNumbers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJournalNumberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // The prefix rule ignores checking the prefix of the journal number to be updated
        return [
            'name' =>          ['required', 'string'],
            'prefix'        => [
                'required',
                'string',
                Rule::unique('journal_numbers', 'prefix')
                    ->where('company_id', $this->attributes->get('company_id'))
                    ->ignore($this->route('journal_number')->id)
                    ->whereNull('deleted_at')
            ],
            'resetInterval' => ['required', 'string', 'in:daily,weekly,monthly,yearly'],
            'status' =>        ['required', 'string', 'in:active,inactive'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorize if the user's company ID matches this journal number's company ID
        $userCompanyId = $this->attributes->get('companyId');
        $numberCompanyId = $this->route('journalNumber')->company_id;

        return $userCompanyId === $numberCompanyId;
    }
}

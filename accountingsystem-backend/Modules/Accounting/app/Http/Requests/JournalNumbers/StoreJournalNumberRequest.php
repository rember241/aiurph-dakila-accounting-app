<?php

namespace Modules\Accounting\Http\Requests\JournalNumbers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJournalNumberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Prefix rule checks the uniqueness of prefix of the new journal number against non-soft-deleted journal numbers
        return [
            'name' =>          ['required', 'string'],
            'prefix' =>        [
                'required',
                'string',
                Rule::unique('journal_numbers', 'prefix')
                    ->where('company_id', $this->attributes->get('company_id'))
                    ->whereNull('deleted_at'),
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
        return true;
    }
}

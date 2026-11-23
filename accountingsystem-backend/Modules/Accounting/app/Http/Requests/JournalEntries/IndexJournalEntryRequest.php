<?php

namespace Modules\Accounting\Http\Requests\JournalEntries;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'sort' => ['nullable', 'in:date'],
            'order' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:posted,draft,void'],
            'type' => ['nullable', 'in:general,adjusting,closing'],
            'per_page' => ['nullable', 'integer', 'in:5,10,20,30,50'],
        ];
    }
}
